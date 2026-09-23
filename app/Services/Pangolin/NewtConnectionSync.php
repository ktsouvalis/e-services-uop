<?php

namespace App\Services\Pangolin;

use App\Models\PangolinNewtAgent;
use App\Models\PangolinNewtConnection;
use Carbon\Carbon;
use Throwable;

/**
 * Orchestrates a full Newt Connections sync: pull each admin-managed
 * App\Models\PangolinNewtAgent's `docker logs newt` over SSH, parse ACCESS
 * sessions (NewtAccessLogParser), resolve identities via a direct Postgres
 * read against Pangolin's own DB (NewtAccessLogResolver), and upsert into
 * pangolin_newt_connections keyed by (newt_agent_id, session_id) — so
 * repeated fetches never duplicate a session, only fill in ended_at for one
 * that was still open last time. Dispatched by
 * App\Jobs\Pangolin\FetchNewtConnections. See CLAUDE.md's Pangolin module
 * section.
 */
class NewtConnectionSync
{
    public function __construct(
        private readonly SshCommandRunner $ssh,
        private readonly NewtAccessLogParser $parser,
        private readonly NewtAccessLogResolver $resolver,
    ) {
    }

    /** @return array{synced: int, agent_errors: array<int, string>} */
    public function run(): array
    {
        $agents = PangolinNewtAgent::all();
        [$siteMap, $clientMap, $targetMap] = $this->buildLookupMaps();

        $lookback = config('pangolin.newt_log_lookback_hours');
        $synced = 0;
        $agentErrors = [];

        foreach ($agents as $agent) {
            try {
                $raw = $this->ssh->run(
                    $agent->ip,
                    config('pangolin.newt_ssh.username'),
                    config('pangolin.newt_ssh.key_path'),
                    "docker logs --since {$lookback}h newt 2>&1",
                );
            } catch (Throwable $e) {
                $agentErrors[] = "{$agent->name} ({$agent->ip}): {$e->getMessage()}";

                continue;
            }

            // Which physical site this agent's connections actually went
            // through — computed once per agent, not per session, since a
            // resource's own DB row can't tell us this (see
            // NewtAccessLogResolver::resolve()'s docblock: every resource
            // spans every org site for HA, so there is no single "site" to
            // look up per-resource). Each Newt agent IS one specific site's
            // exit node, so matching the agent's name against the fetched
            // site names is the reliable source of truth here.
            $agentSiteName = $this->matchSiteForAgent($agent->name, $siteMap);

            foreach ($this->parser->parse($raw) as $session) {
                $startedAt = $this->parseTimestamp($session['started']);
                if (! $startedAt) {
                    continue;
                }

                $identity = $this->resolver->resolve($session, $siteMap, $clientMap, $targetMap);
                $identity['site_name'] = $agentSiteName ?? $identity['site_name'];

                PangolinNewtConnection::updateOrCreate(
                    ['newt_agent_id' => $agent->id, 'session_id' => $session['session']],
                    [
                        'agent_name' => $agent->name,
                        'agent_ip' => $agent->ip,
                        'resource_id' => $session['resource_id'],
                        'proto' => $session['proto'],
                        'src_ip' => $session['src_ip'],
                        'src_port' => $session['src_port'],
                        'dst_ip' => $session['dst_ip'],
                        'dst_port' => $session['dst_port'],
                        'started_at' => $startedAt,
                        'ended_at' => $session['ended'] ? $this->parseTimestamp($session['ended']) : null,
                        ...$identity,
                    ],
                );
                $synced++;
            }
        }

        return ['synced' => $synced, 'agent_errors' => $agentErrors];
    }

    /** @return array{0: array<int, string>, 1: array<string, array{user_name: ?string, user_email: ?string, client_name: string}>, 2: array<int, string>} */
    private function buildLookupMaps(): array
    {
        try {
            $pdo = $this->resolver->connect();

            return $this->resolver->buildLookupMaps($pdo);
        } catch (Throwable $e) {
            // Falls back to raw IPs/IDs (resolve() handles empty maps
            // gracefully) rather than failing the whole sync — matches
            // logs_viewer.py's own documented fallback when Postgres isn't
            // reachable from wherever this runs.
            report($e);

            return [[], [], []];
        }
    }

    /**
     * Case-insensitive substring match of an agent's name (e.g. "patra")
     * against the real site names fetched from Postgres (e.g. "Patras") —
     * the two naming conventions aren't identical, so this can't be an
     * exact-match lookup. Reliable in practice for this org's 3-site setup;
     * if it ever stops matching (a renamed site or a differently-named
     * agent), the fallback is just resolve()'s own null/last-resort
     * site_name, not a crash.
     *
     * @param  array<int, string>  $siteMap
     */
    private function matchSiteForAgent(string $agentName, array $siteMap): ?string
    {
        $needle = strtolower($agentName);
        foreach ($siteMap as $siteName) {
            if (str_contains(strtolower($siteName), $needle) || str_contains($needle, strtolower($siteName))) {
                return $siteName;
            }
        }

        return null;
    }

    private function parseTimestamp(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
