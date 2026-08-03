<?php

namespace App\Services\Authentik;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Native PHP port of authentik-utils' monitor.py check_* functions.
 * monitor.py itself is a Textual TUI with no headless/JSON mode, so this
 * re-implements its HTTP checks directly rather than shelling out.
 *
 * Patroni's replication-slots check (direct psycopg2/Postgres connection)
 * is intentionally unported — same call made for Pangolin's Postgres-direct
 * check: it's an optional enhancement (monitor.py itself degrades to "no
 * slot data" without Postgres credentials) and porting it would need a new
 * pdo_pgsql dependency for a cosmetic detail. Patroni's failover-history
 * endpoint (/history) IS ported below since it's plain HTTP, no Postgres
 * needed.
 */
class ClusterMonitor
{
    private int $timeout;

    public function __construct()
    {
        $this->timeout = (int) config('authentik.http_timeout', 5);
    }

    /**
     * Run every check and return a flat list of rows shaped like
     * AuthentikMonitorStatus columns (service, node_name, node_ip, status, role, metrics, message).
     */
    public function run(): array
    {
        $rows = [];
        $nodes = config('authentik.nodes', []);

        foreach ($nodes as $node) {
            $rows[] = $this->checkKeepalivedNode($node);
            $rows[] = $this->checkAuthentikNode($node);
            $rows[] = $this->checkPatroniNode($node);
            $rows[] = $this->checkEtcdNode($node);
            $rows[] = $this->checkHaproxyNode($node);
            $rows[] = $this->checkNginxStatus($node);
        }

        if ($vip = config('authentik.vip')) {
            $rows[] = $this->checkVipHolder($vip);
        }

        $rows[] = $this->checkAuthentikWorkers($nodes);
        $rows[] = $this->checkAuthentikTaskQueue();

        // Patroni history needs the primary's IP, only known after the
        // patroni checks above have run.
        $primary = collect($rows)->first(fn ($r) => $r['service'] === 'patroni' && $r['role'] === 'primary');
        if ($primary) {
            $history = $this->checkPatroniHistory($primary['node_ip']);
            if ($history) {
                $rows[] = $history;
            }
        }

        return $rows;
    }

    private function httpOk(string $url): bool
    {
        try {
            return Http::timeout($this->timeout)->withoutVerifying()->get($url)->status() === 200;
        } catch (Throwable) {
            return false;
        }
    }

    private function checkKeepalivedNode(array $node): array
    {
        $nginxUp = $this->httpOk("https://{$node['ip']}/monitor");
        $base = (int) ($node['base_priority'] ?? 100);
        $trackWeight = (int) config('authentik.keepalived.track_weight', -20);
        $effective = $nginxUp ? $base : $base + $trackWeight;

        return $this->row('keepalived', $node['name'], $node['ip'], $nginxUp ? 'up' : 'degraded', metrics: [
            'base_priority' => $base,
            'effective_priority' => $effective,
        ]);
    }

    private function checkVipHolder(string $vip): array
    {
        try {
            $response = Http::timeout($this->timeout)->withoutVerifying()->get("https://{$vip}/monitor");
            if ($response->status() === 200) {
                $data = $response->json();

                return $this->row('vip', 'VIP', $vip, 'up', metrics: [
                    'holder_name' => $data['node'] ?? '?',
                    'holder_ip' => $data['ip'] ?? '?',
                ]);
            }
        } catch (Throwable) {
            // falls through to the down row below
        }

        return $this->row('vip', 'VIP', $vip, 'down');
    }

    private function checkPatroniNode(array $node): array
    {
        $port = config('authentik.ports.patroni');

        try {
            $data = Http::timeout($this->timeout)->get("http://{$node['ip']}:{$port}/")->json();
            $rawRole = $data['role'] ?? 'unknown';
            $isLeader = in_array($rawRole, ['primary', 'master', 'standby_leader'], true);
            $role = $isLeader ? 'primary' : 'replica';
            $state = $data['replication_state'] ?? ($data['state'] ?? 'unknown');

            $lagBytes = null;
            if (! $isLeader) {
                $received = $data['xlog']['received_location'] ?? null;
                $replayed = $data['xlog']['replayed_location'] ?? null;
                if ($received !== null && $replayed !== null) {
                    $lagBytes = max(0, $received - $replayed);
                }
            }

            // A leader should be "running"; a replica should be "streaming".
            // Anything else (stuck "starting" after a timeline mismatch,
            // crash loop, ...) is a real problem even though Patroni answers.
            $healthy = ($isLeader && $state === 'running') || (! $isLeader && $state === 'streaming');

            return $this->row('patroni', $node['name'], $node['ip'], $healthy ? 'up' : 'degraded', role: $role, metrics: [
                'state' => $state,
                'timeline' => $data['timeline'] ?? null,
                'pending_restart' => $data['pending_restart'] ?? false,
                'lag_bytes' => $lagBytes,
                'healthy' => $healthy,
            ]);
        } catch (Throwable) {
            return $this->row('patroni', $node['name'], $node['ip'], 'down', role: 'down', message: 'unreachable');
        }
    }

    private function checkPatroniHistory(string $primaryIp): ?array
    {
        $port = config('authentik.ports.patroni');

        try {
            $entries = Http::timeout($this->timeout)->get("http://{$primaryIp}:{$port}/history")->json();
            if (empty($entries)) {
                return null;
            }
            $last = end($entries);
            $vip = config('authentik.vip') ?: $primaryIp;

            return $this->row('patroni_history', 'History', $vip, 'up', metrics: [
                'timeline' => $last[0] ?? '?',
                'reason' => $last[2] ?? 'unknown',
                'timestamp' => $last[3] ?? null,
            ]);
        } catch (Throwable) {
            return null;
        }
    }

    private function checkEtcdNode(array $node): array
    {
        $port = config('authentik.ports.etcd');

        try {
            $health = Http::timeout($this->timeout)->get("http://{$node['ip']}:{$port}/health")->json();
            $healthy = in_array($health['health'] ?? null, [true, 'true'], true);
        } catch (Throwable) {
            return $this->row('etcd', $node['name'], $node['ip'], 'down');
        }

        $isLeader = false;
        $raftTerm = null;
        $dbKb = 0;

        try {
            // etcd's v3 JSON gateway wants a JSON *object* body (even though
            // this request type has no fields) — Http::post($url, [])
            // serializes an empty PHP array as `[]`, which etcd's Go server
            // rejects. withBody('{}', ...) forces an actual empty object.
            $status = Http::timeout($this->timeout)
                ->withBody('{}', 'application/json')
                ->post("http://{$node['ip']}:{$port}/v3/maintenance/status")
                ->json();
            $memberId = $status['header']['member_id'] ?? null;
            $leaderId = $status['leader'] ?? null;
            $isLeader = $memberId && $leaderId && $memberId === $leaderId;
            $raftTerm = $status['raftTerm'] ?? null;
            $dbKb = intdiv((int) ($status['dbSizeInUse'] ?? 0), 1024);
        } catch (Throwable) {
            // leave maintenance-status fields at their defaults
        }

        return $this->row('etcd', $node['name'], $node['ip'], $healthy ? 'up' : 'down', metrics: [
            'leader' => $isLeader,
            'raft_term' => $raftTerm,
            'db_kb' => $dbKb,
        ]);
    }

    private function checkHaproxyNode(array $node): array
    {
        $port = config('authentik.ports.haproxy_stats');
        $user = config('authentik.credentials.haproxy_stats_user');
        $pass = config('authentik.credentials.haproxy_stats_pass');

        try {
            $response = Http::timeout($this->timeout)
                ->withBasicAuth($user, $pass)
                ->get("http://{$node['ip']}:{$port}/stats;csv");
            if (! $response->ok()) {
                return $this->row('haproxy', $node['name'], $node['ip'], 'down');
            }

            $backends = [];

            foreach (explode("\n", $response->body()) as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                $parts = str_getcsv($line);
                if (count($parts) < 18) {
                    continue;
                }
                [$pxname, $svname] = [$parts[0], $parts[1]];
                $status = $parts[17];
                if (in_array($svname, ['FRONTEND', 'BACKEND'], true)) {
                    continue;
                }
                $backends[$pxname][] = ['server' => $svname, 'status' => $status];
            }

            // A backend pool with 0 UP servers is a real problem. Partial UP
            // counts within a pool are role-based and expected (e.g. exactly
            // 1 UP in a Patroni primary pool) — mirrors monitor.py's
            // HAProxyPanel "any_zero" logic exactly.
            $anyPoolFullyDown = false;
            foreach ($backends as $servers) {
                $ups = count(array_filter($servers, fn ($s) => $s['status'] === 'UP'));
                if ($ups === 0) {
                    $anyPoolFullyDown = true;
                    break;
                }
            }

            return $this->row('haproxy', $node['name'], $node['ip'], $anyPoolFullyDown ? 'degraded' : 'up', metrics: [
                'backends' => $backends,
            ]);
        } catch (Throwable) {
            return $this->row('haproxy', $node['name'], $node['ip'], 'down');
        }
    }

    private function checkAuthentikNode(array $node): array
    {
        $port = config('authentik.ports.authentik');
        $ok = $this->httpOk("https://{$node['ip']}:{$port}/-/health/live/");

        return $this->row('authentik', $node['name'], $node['ip'], $ok ? 'up' : 'down');
    }

    private function checkNginxStatus(array $node): array
    {
        $port = config('authentik.ports.nginx_status');

        try {
            $response = Http::timeout(2)->get("http://{$node['ip']}:{$port}/nginx_status");
            if (! $response->ok()) {
                return $this->row('nginx', $node['name'], $node['ip'], 'down');
            }
            $text = $response->body();
            preg_match('/Active connections:\s+(\d+)/', $text, $active);
            preg_match('/Reading:\s+(\d+)/', $text, $reading);
            preg_match('/Writing:\s+(\d+)/', $text, $writing);
            preg_match('/Waiting:\s+(\d+)/', $text, $waiting);

            return $this->row('nginx', $node['name'], $node['ip'], 'up', metrics: [
                'active' => (int) ($active[1] ?? 0),
                'reading' => (int) ($reading[1] ?? 0),
                'writing' => (int) ($writing[1] ?? 0),
                'waiting' => (int) ($waiting[1] ?? 0),
            ]);
        } catch (Throwable) {
            return $this->row('nginx', $node['name'], $node['ip'], 'down');
        }
    }

    /**
     * /api/v3/tasks/tasks/status/ — background task health (email, outpost
     * sync, policy cache...). Rejected/errored tasks don't affect
     * /health/ready but cause silent UX failures.
     */
    private function checkAuthentikTaskQueue(): array
    {
        $vip = config('authentik.vip');
        $port = config('authentik.ports.authentik');
        $token = config('authentik.credentials.authentik_api_token');

        if (! $token) {
            return $this->row('worker_queue', 'Worker Queue', $vip ?: '-', 'unknown', message: 'no token configured');
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withoutVerifying()
                ->withToken($token)
                ->get("https://{$vip}:{$port}/api/v3/tasks/tasks/status/");

            if (in_array($response->status(), [401, 403], true)) {
                return $this->row('worker_queue', 'Worker Queue', $vip, 'down', message: 'unauthorized — token needs superuser permissions');
            }
            if (! $response->ok()) {
                return $this->row('worker_queue', 'Worker Queue', $vip, 'down', message: "HTTP {$response->status()}");
            }

            $d = $response->json();
            $error = (int) ($d['error'] ?? 0);
            $rejected = (int) ($d['rejected'] ?? 0);
            $warning = (int) ($d['warning'] ?? 0);
            $status = $error > 0 ? 'down' : (($rejected > 0 || $warning > 0) ? 'degraded' : 'up');

            return $this->row('worker_queue', 'Worker Queue', $vip, $status, metrics: [
                'queued' => (int) ($d['queued'] ?? 0),
                'running' => (int) ($d['running'] ?? 0),
                'rejected' => $rejected,
                'error' => $error,
                'warning' => $warning,
                'done' => (int) ($d['done'] ?? 0),
                'consumed' => (int) ($d['consumed'] ?? 0),
            ]);
        } catch (Throwable $e) {
            return $this->row('worker_queue', 'Worker Queue', $vip, 'down', message: $e->getMessage());
        }
    }

    /**
     * /api/v3/tasks/workers/ — workers currently connected via the broker
     * heartbeat. The only reliable signal a worker is actually consuming
     * tasks (the :9080 liveness probe stays 200 even when the dramatiq
     * consumer is dead). worker_id format is "<uuid>@<hostname>", mapped
     * back to a node so any node with zero connected workers is flagged.
     */
    private function checkAuthentikWorkers(array $nodes): array
    {
        $vip = config('authentik.vip');
        $port = config('authentik.ports.authentik');
        $token = config('authentik.credentials.authentik_api_token');
        $expected = array_map(fn ($n) => $n['name'] ?? $n['ip'], $nodes);

        if (! $token) {
            return $this->row('workers', 'Workers', $vip ?: '-', 'unknown', message: 'no token configured', metrics: [
                'count' => 0, 'expected' => count($expected), 'present' => [], 'missing' => $expected, 'mismatched' => [],
            ]);
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withoutVerifying()
                ->withToken($token)
                ->get("https://{$vip}:{$port}/api/v3/tasks/workers/");

            if (in_array($response->status(), [401, 403], true)) {
                return $this->row('workers', 'Workers', $vip, 'down', message: 'unauthorized — token needs admin perms', metrics: [
                    'count' => 0, 'expected' => count($expected), 'present' => [], 'missing' => $expected, 'mismatched' => [],
                ]);
            }
            if (! $response->ok()) {
                return $this->row('workers', 'Workers', $vip, 'down', message: "HTTP {$response->status()}", metrics: [
                    'count' => 0, 'expected' => count($expected), 'present' => [], 'missing' => $expected, 'mismatched' => [],
                ]);
            }

            $workers = is_array($response->json()) ? $response->json() : [];
            $present = [];
            $mismatched = [];
            foreach ($workers as $w) {
                $wid = $w['worker_id'] ?? '';
                $node = str_contains($wid, '@') ? explode('@', $wid, 2)[1] : $wid;
                $present[] = $node;
                if (($w['version_matching'] ?? true) === false) {
                    $mismatched[] = $node;
                }
            }
            $missing = array_values(array_diff($expected, $present));
            $status = $missing ? 'down' : ($mismatched ? 'degraded' : 'up');

            return $this->row('workers', 'Workers', $vip, $status, metrics: [
                'count' => count($workers),
                'expected' => count($expected),
                'present' => $present,
                'missing' => $missing,
                'mismatched' => $mismatched,
            ]);
        } catch (Throwable $e) {
            return $this->row('workers', 'Workers', $vip, 'down', message: $e->getMessage(), metrics: [
                'count' => 0, 'expected' => count($expected), 'present' => [], 'missing' => $expected, 'mismatched' => [],
            ]);
        }
    }

    private function row(
        string $service,
        string $nodeName,
        string $nodeIp,
        string $status,
        ?string $role = null,
        ?array $metrics = null,
        ?string $message = null,
    ): array {
        return [
            'service' => $service,
            'node_name' => $nodeName,
            'node_ip' => $nodeIp,
            'status' => $status,
            'role' => $role,
            'metrics' => $metrics,
            'message' => $message,
            'checked_at' => now(),
        ];
    }
}
