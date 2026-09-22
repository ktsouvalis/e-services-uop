<?php

namespace App\Services\Pangolin;

use App\Models\PangolinMonitorSettings;
use App\Models\PangolinNewtAgent;
use App\Services\Concerns\ClusterMonitorHelpers;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;
use Throwable;

/**
 * Native PHP polling for the Monitor tab — single-node, admin-configurable
 * (node IP / Pangolin URL / API key persisted in pangolin_monitor_settings,
 * editable via a form on the Monitor tab), same shape as
 * App\Services\Authentik\ClusterMonitor, plus Newt agent SSH reachability
 * checks (unchanged from the original 3-node version — Newt agents are
 * separate remote hosts, unrelated to Pangolin node HA, so they were never
 * part of what "leave HA outside" dropped).
 *
 * This deliberately dropped the original 3-node HA panel set (keepalived
 * master election, Patroni leader/replica + lag, etcd raft/leader, HAProxy
 * per-pool backend health) — the Monitor tab now always targets a single
 * Pangolin node, same as Authentik's. The real multi-node HA topology
 * (config('pangolin.nodes') / .vip / .keepalived /
 * .ports.{patroni,etcd,haproxy_stats}) is untouched and still used by
 * ConfigYamlWriter for the Logs tab's config.yml, which genuinely does fetch
 * from every physical node in the real cluster — that's a separate concern
 * from what this class checks.
 */
class ClusterMonitor
{
    use ClusterMonitorHelpers;

    private int $timeout;

    public function __construct()
    {
        $this->timeout = (int) config('pangolin.http_timeout', 5);
    }

    public function run(): array
    {
        $rows = [];

        $settings = PangolinMonitorSettings::first();
        $ip = $settings?->node_ip;

        if (! $ip) {
            $rows[] = $this->row('pangolin', 'Pangolin', '-', 'unknown', message: 'no node IP configured — see Monitor settings');
        } else {
            $port = config('pangolin.ports.pangolin', 3001);
            $url = $settings->pangolin_url ? rtrim($settings->pangolin_url, '/') : null;
            $apiKey = $settings->api_key ? Crypt::decryptString($settings->api_key) : null;
            $orgSlug = config('pangolin.org_slug');

            $responses = Http::pool(function ($pool) use ($ip, $port, $url, $apiKey, $orgSlug) {
                $requests = [
                    'pangolin' => $pool->as('pangolin')->timeout($this->timeout)
                        ->get("http://{$ip}:{$port}/api/v1/"),
                ];
                if ($url && $apiKey && $orgSlug) {
                    $requests['api'] = $pool->as('api')->timeout($this->timeout)->withToken($apiKey)
                        ->get("{$url}/v1/org/{$orgSlug}/site-resources", ['pageSize' => 1, 'page' => 1]);
                }

                return $requests;
            });

            $rows[] = $this->parsePangolin($ip, $responses['pangolin']);
            $rows[] = $this->parseGerbil($ip, $responses['pangolin']);
            $rows[] = $this->parseApi($ip, $url, $apiKey, $orgSlug, $responses['api'] ?? null);
        }

        foreach (PangolinNewtAgent::all() as $agent) {
            $rows[] = $this->checkNewtNode($agent);
        }

        return $rows;
    }

    /**
     * Newt agents have no HTTP endpoint — reachability is checked by SSH
     * login only, no container inspection. Unchanged from the original
     * 3-node ClusterMonitor other than reading agents from the DB (admin-
     * managed, arbitrary count) instead of a fixed env list. Falls back to
     * the cluster-node SSH credentials when no Newt-specific ones are
     * configured, same as ConfigYamlWriter does for logs_viewer.py's Newt
     * access-log fetch.
     */
    private function checkNewtNode(PangolinNewtAgent $agent): array
    {
        $username = config('pangolin.newt.ssh.username') ?: config('pangolin.ssh.username');
        $keyPath = config('pangolin.newt.ssh.key_path') ?: config('pangolin.ssh.key_path');

        try {
            $ssh = new SSH2($agent->ip, 22, $this->timeout);
            $key = PublicKeyLoader::load(file_get_contents($keyPath));
            $authenticated = $ssh->login($username, $key);
            $ssh->disconnect();

            return $this->row('newt', $agent->name, $agent->ip, $authenticated ? 'up' : 'down');
        } catch (Throwable $e) {
            return $this->row('newt', $agent->name, $agent->ip, 'down', message: substr($e->getMessage(), 0, 120));
        }
    }

    private function ok(Response|Throwable $response, array $statuses = [200, 401, 403]): bool
    {
        return $response instanceof Response && in_array($response->status(), $statuses, true);
    }

    private function parsePangolin(string $ip, Response|Throwable $response): array
    {
        return $this->row('pangolin', 'Pangolin', $ip, $this->ok($response) ? 'up' : 'down');
    }

    /**
     * Gerbil has no HTTP health endpoint of its own — inferred from the
     * Pangolin API response, same as the original 3-node ClusterMonitor did
     * (they share the same compose stack, so one being reachable is a
     * reasonable proxy for the other on a single node).
     */
    private function parseGerbil(string $ip, Response|Throwable $response): array
    {
        return $this->row('gerbil', 'Gerbil', $ip, $this->ok($response) ? 'up' : 'down', message: 'inferred from pangolin API');
    }

    /**
     * Authenticated reachability + API-key-validity check against the
     * Pangolin Integration API — mirrors Authentik's bearer-token workers/
     * task-queue checks, using the only documented authenticated endpoint
     * this app already calls (see PangolinApiClient::listSiteResources()).
     * org_slug comes from config (static per deployment, unlike node_ip/url/
     * api_key which the admin form treats as "which server am I pointing
     * at today") — pageSize=1 keeps this a cheap reachability probe, not a
     * real listing.
     */
    private function parseApi(string $ip, ?string $url, ?string $apiKey, ?string $orgSlug, Response|Throwable|null $response): array
    {
        if (! $url || ! $apiKey || ! $orgSlug) {
            return $this->row('api', 'Integration API', $ip, 'unknown', message: 'no URL/API key configured');
        }
        if ($response === null) {
            return $this->row('api', 'Integration API', $ip, 'down', message: 'request not sent');
        }
        if ($response instanceof Response && in_array($response->status(), [401, 403], true)) {
            return $this->row('api', 'Integration API', $ip, 'down', message: 'unauthorized — check the API key');
        }
        if (! ($response instanceof Response && $response->status() === 200)) {
            $status = $response instanceof Response ? $response->status() : 'connection error';

            return $this->row('api', 'Integration API', $ip, 'down', message: "HTTP {$status}");
        }

        $total = 0;
        try {
            $total = (int) ($response->json('data.pagination.total') ?? 0);
        } catch (Throwable) {
            // leave total at 0 — the reachability/auth result above is what matters
        }

        return $this->row('api', 'Integration API', $ip, 'up', metrics: ['total_resources' => $total]);
    }
}
