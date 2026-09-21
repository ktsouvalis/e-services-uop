<?php

namespace App\Services\Authentik;

use App\Models\AuthentikMonitorSettings;
use App\Services\Concerns\ClusterMonitorHelpers;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Native PHP polling for this (single-node) Authentik deployment — replaced
 * shelling out to `akropolis monitor` behind a browser terminal (2026-09-21,
 * same day it was introduced) with the same native-HTTP-checks approach
 * Pangolin's ClusterMonitor already uses, driven by three DB-persisted
 * settings (node IP, public Authentik URL, API token — see
 * AuthentikMonitorSettings, editable via an admin form) instead of pasting a
 * config.<site>.monitor.yml per run. See CLAUDE.md's Authentik module
 * section for the full history.
 *
 * Only 4 checks, not the full HA panel set the original 3-node
 * ClusterMonitor had (no Patroni/etcd/HAProxy/keepalived) — this deployment
 * is single-node, so most of that machinery doesn't apply. There is
 * deliberately no direct worker-liveness port check (an earlier version
 * probed one, modeled on the pasted config.<site>.monitor.yml this was
 * built from) — this deployment's worker doesn't actually expose one, found
 * live (it showed a flat "down" that was really just "nothing answers
 * there", not a real fault). The `workers` API check below is the real
 * signal anyway — see its own docblock.
 */
class ClusterMonitor
{
    use ClusterMonitorHelpers;

    private int $timeout;

    public function __construct()
    {
        $this->timeout = (int) config('authentik.monitor.http_timeout', 5);
    }

    public function run(): array
    {
        $settings = AuthentikMonitorSettings::first();
        $ip = $settings?->node_ip;

        if (! $ip) {
            return [$this->row('authentik', 'Authentik', '-', 'unknown', message: 'no node IP configured — see Monitor settings')];
        }

        $url = $settings->authentik_url ? rtrim($settings->authentik_url, '/') : null;
        $token = $settings->api_token ? Crypt::decryptString($settings->api_token) : null;
        $ports = config('authentik.monitor.ports');

        $responses = Http::pool(function ($pool) use ($ip, $url, $token, $ports) {
            $requests = [
                'authentik' => $pool->as('authentik')->timeout($this->timeout)->withoutVerifying()
                    ->get("https://{$ip}:{$ports['authentik']}/-/health/live/"),
                'nginx' => $pool->as('nginx')->timeout(2)
                    ->get("http://{$ip}:{$ports['nginx_status']}/nginx_status"),
            ];
            if ($url && $token) {
                $requests['workers'] = $pool->as('workers')->timeout($this->timeout)->withToken($token)
                    ->get("{$url}/api/v3/tasks/workers/");
                $requests['taskqueue'] = $pool->as('taskqueue')->timeout($this->timeout)->withToken($token)
                    ->get("{$url}/api/v3/tasks/tasks/status/");
            }

            return $requests;
        });

        return [
            $this->parseAuthentik($ip, $responses['authentik']),
            $this->parseNginxStatus($ip, $responses['nginx']),
            $this->parseApiWorkers($ip, $token, $responses['workers'] ?? null),
            $this->parseTaskQueue($ip, $token, $responses['taskqueue'] ?? null),
        ];
    }

    private function ok(Response|Throwable $response, array $statuses = [200]): bool
    {
        return $response instanceof Response && in_array($response->status(), $statuses, true);
    }

    private function json(Response|Throwable $response): ?array
    {
        if (! $response instanceof Response) {
            return null;
        }
        try {
            return $response->json();
        } catch (Throwable) {
            return null;
        }
    }

    private function parseAuthentik(string $ip, Response|Throwable $response): array
    {
        $ok = $this->ok($response, [200, 204]);

        return $this->row('authentik', 'Authentik', $ip, $ok ? 'up' : 'down');
    }

    private function parseNginxStatus(string $ip, Response|Throwable $response): array
    {
        if (! $this->ok($response)) {
            return $this->row('nginx', 'Nginx', $ip, 'down');
        }

        $text = $response->body();
        preg_match('/Active connections:\s+(\d+)/', $text, $active);
        preg_match('/Reading:\s+(\d+)/', $text, $reading);
        preg_match('/Writing:\s+(\d+)/', $text, $writing);
        preg_match('/Waiting:\s+(\d+)/', $text, $waiting);

        return $this->row('nginx', 'Nginx', $ip, 'up', metrics: [
            'active' => (int) ($active[1] ?? 0),
            'reading' => (int) ($reading[1] ?? 0),
            'writing' => (int) ($writing[1] ?? 0),
            'waiting' => (int) ($waiting[1] ?? 0),
        ]);
    }

    /**
     * /api/v3/tasks/workers/ — workers currently connected via the broker
     * heartbeat. The only worker-health signal this module has at all: a
     * direct port-liveness probe was tried first but dropped (see this
     * class's docblock) — this API check is more reliable anyway, since a
     * liveness port can stay green even when the dramatiq consumer itself
     * is dead.
     */
    private function parseApiWorkers(string $ip, ?string $token, Response|Throwable|null $response): array
    {
        if (! $token) {
            return $this->row('workers', 'Workers', $ip, 'unknown', message: 'no API token configured', metrics: [
                'count' => 0, 'present' => [],
            ]);
        }
        if ($response === null) {
            return $this->row('workers', 'Workers', $ip, 'down', message: 'request not sent', metrics: [
                'count' => 0, 'present' => [],
            ]);
        }
        if ($response instanceof Response && in_array($response->status(), [401, 403], true)) {
            return $this->row('workers', 'Workers', $ip, 'down', message: 'unauthorized — token needs admin perms', metrics: [
                'count' => 0, 'present' => [],
            ]);
        }
        if (! $this->ok($response)) {
            $status = $response instanceof Response ? $response->status() : 'connection error';

            return $this->row('workers', 'Workers', $ip, 'down', message: "HTTP {$status}", metrics: [
                'count' => 0, 'present' => [],
            ]);
        }

        $workers = $this->json($response) ?? [];
        $workers = is_array($workers) ? $workers : [];
        $present = array_map(fn ($w) => $w['worker_id'] ?? '?', $workers);
        $mismatched = array_filter($workers, fn ($w) => ($w['version_matching'] ?? true) === false);
        $status = ! $workers ? 'down' : ($mismatched ? 'degraded' : 'up');

        return $this->row('workers', 'Workers', $ip, $status, metrics: [
            'count' => count($workers),
            'present' => $present,
        ]);
    }

    /**
     * /api/v3/tasks/tasks/status/ — background task health (email, outpost
     * sync, policy cache...). Rejected/errored tasks don't affect
     * /health/ready but cause silent UX failures.
     */
    private function parseTaskQueue(string $ip, ?string $token, Response|Throwable|null $response): array
    {
        if (! $token) {
            return $this->row('worker_queue', 'Worker Queue', $ip, 'unknown', message: 'no API token configured');
        }
        if ($response === null) {
            return $this->row('worker_queue', 'Worker Queue', $ip, 'down', message: 'request not sent');
        }
        if ($response instanceof Response && in_array($response->status(), [401, 403], true)) {
            return $this->row('worker_queue', 'Worker Queue', $ip, 'down', message: 'unauthorized — token needs superuser permissions');
        }
        if (! $this->ok($response)) {
            $status = $response instanceof Response ? $response->status() : 'connection error';

            return $this->row('worker_queue', 'Worker Queue', $ip, 'down', message: "HTTP {$status}");
        }

        $d = $this->json($response) ?? [];
        $error = (int) ($d['error'] ?? 0);
        $rejected = (int) ($d['rejected'] ?? 0);
        $warning = (int) ($d['warning'] ?? 0);
        $status = $error > 0 ? 'down' : (($rejected > 0 || $warning > 0) ? 'degraded' : 'up');

        return $this->row('worker_queue', 'Worker Queue', $ip, $status, metrics: [
            'queued' => (int) ($d['queued'] ?? 0),
            'running' => (int) ($d['running'] ?? 0),
            'rejected' => $rejected,
            'error' => $error,
            'warning' => $warning,
            'done' => (int) ($d['done'] ?? 0),
        ]);
    }
}
