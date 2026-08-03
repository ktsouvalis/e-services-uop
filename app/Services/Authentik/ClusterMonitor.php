<?php

namespace App\Services\Authentik;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Native PHP port of authentik-utils' monitor.py check_* functions.
 * monitor.py itself is a Textual TUI with no headless/JSON mode, so this
 * re-implements its HTTP checks directly rather than shelling out.
 *
 * All per-node HTTP requests are fired concurrently via Http::pool() (one
 * batch per run), not sequentially — this matters a lot in practice: this
 * cluster isn't fully reachable from every network the app is deployed on
 * (e.g. the production host currently only has a route to ports 443/9000 on
 * these nodes, not 9443/8008/2379/8080 — a firewall gap, not a bug here).
 * With ~20 checks run sequentially, several blocked ports each burning their
 * full timeout would alone exceed PollCluster's 60s job timeout, killing the
 * job (and the whole queue-worker process along with it, via pcntl) before
 * a single row got written — "no data ever, forever" instead of "some rows
 * correctly show down". Concurrency bounds total wall time to ~one timeout
 * regardless of how many endpoints are unreachable.
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
        $nodes = config('authentik.nodes', []);
        $vip = config('authentik.vip');
        $token = config('authentik.credentials.authentik_api_token');
        $akPort = config('authentik.ports.authentik');

        $responses = Http::pool(function ($pool) use ($nodes, $vip, $token, $akPort) {
            $requests = [];
            foreach ($nodes as $node) {
                $ip = $node['ip'];
                $requests["keepalived:{$ip}"] = $pool->as("keepalived:{$ip}")->timeout($this->timeout)->withoutVerifying()->get("https://{$ip}/monitor");
                $requests["authentik:{$ip}"] = $pool->as("authentik:{$ip}")->timeout($this->timeout)->withoutVerifying()->get("https://{$ip}:{$akPort}/-/health/live/");
                $requests["patroni:{$ip}"] = $pool->as("patroni:{$ip}")->timeout($this->timeout)->get("http://{$ip}:".config('authentik.ports.patroni').'/');
                $requests["etcd_health:{$ip}"] = $pool->as("etcd_health:{$ip}")->timeout($this->timeout)->get("http://{$ip}:".config('authentik.ports.etcd').'/health');
                $requests["etcd_maint:{$ip}"] = $pool->as("etcd_maint:{$ip}")->timeout($this->timeout)->withBody('{}', 'application/json')->post("http://{$ip}:".config('authentik.ports.etcd').'/v3/maintenance/status');
                $requests["haproxy:{$ip}"] = $pool->as("haproxy:{$ip}")->timeout($this->timeout)->withBasicAuth(config('authentik.credentials.haproxy_stats_user'), config('authentik.credentials.haproxy_stats_pass'))->get("http://{$ip}:".config('authentik.ports.haproxy_stats').'/stats;csv');
                $requests["nginx:{$ip}"] = $pool->as("nginx:{$ip}")->timeout(2)->get("http://{$ip}:".config('authentik.ports.nginx_status').'/nginx_status');
            }
            if ($vip) {
                $requests['vip'] = $pool->as('vip')->timeout($this->timeout)->withoutVerifying()->get("https://{$vip}/monitor");
                if ($token) {
                    $requests['workers'] = $pool->as('workers')->timeout($this->timeout)->withoutVerifying()->withToken($token)->get("https://{$vip}:{$akPort}/api/v3/tasks/workers/");
                    $requests['taskqueue'] = $pool->as('taskqueue')->timeout($this->timeout)->withoutVerifying()->withToken($token)->get("https://{$vip}:{$akPort}/api/v3/tasks/tasks/status/");
                }
            }

            return $requests;
        });

        $rows = [];
        foreach ($nodes as $node) {
            $rows[] = $this->parseKeepalived($node, $responses["keepalived:{$node['ip']}"]);
            $rows[] = $this->parseAuthentikNode($node, $responses["authentik:{$node['ip']}"]);
            $rows[] = $this->parsePatroniNode($node, $responses["patroni:{$node['ip']}"]);
            $rows[] = $this->parseEtcdNode($node, $responses["etcd_health:{$node['ip']}"], $responses["etcd_maint:{$node['ip']}"]);
            $rows[] = $this->parseHaproxyNode($node, $responses["haproxy:{$node['ip']}"]);
            $rows[] = $this->parseNginxStatus($node, $responses["nginx:{$node['ip']}"]);
        }

        if ($vip) {
            $rows[] = $this->parseVipHolder($vip, $responses['vip']);
            $rows[] = $this->parseAuthentikWorkers($nodes, $vip, $token, $responses['workers'] ?? null);
            $rows[] = $this->parseAuthentikTaskQueue($vip, $token, $responses['taskqueue'] ?? null);
        }

        // Patroni history needs the primary's IP, only known after the
        // patroni checks above have been parsed — one extra request, fired
        // on its own since which node to ask isn't known until now.
        $primary = collect($rows)->first(fn ($r) => $r['service'] === 'patroni' && $r['role'] === 'primary');
        if ($primary) {
            $history = $this->checkPatroniHistory($primary['node_ip']);
            if ($history) {
                $rows[] = $history;
            }
        }

        return $rows;
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

    private function parseKeepalived(array $node, Response|Throwable $response): array
    {
        $nginxUp = $this->ok($response);
        $base = (int) ($node['base_priority'] ?? 100);
        $trackWeight = (int) config('authentik.keepalived.track_weight', -20);
        $effective = $nginxUp ? $base : $base + $trackWeight;

        return $this->row('keepalived', $node['name'], $node['ip'], $nginxUp ? 'up' : 'degraded', metrics: [
            'base_priority' => $base,
            'effective_priority' => $effective,
        ]);
    }

    private function parseVipHolder(string $vip, Response|Throwable $response): array
    {
        if ($this->ok($response) && ($data = $this->json($response))) {
            return $this->row('vip', 'VIP', $vip, 'up', metrics: [
                'holder_name' => $data['node'] ?? '?',
                'holder_ip' => $data['ip'] ?? '?',
            ]);
        }

        return $this->row('vip', 'VIP', $vip, 'down');
    }

    private function parsePatroniNode(array $node, Response|Throwable $response): array
    {
        $data = $this->json($response);
        if (! $response instanceof Response || $data === null) {
            return $this->row('patroni', $node['name'], $node['ip'], 'down', role: 'down', message: 'unreachable');
        }

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

    private function parseEtcdNode(array $node, Response|Throwable $healthResponse, Response|Throwable $maintResponse): array
    {
        $health = $this->json($healthResponse);
        $healthy = $health !== null && in_array($health['health'] ?? null, [true, 'true'], true);

        if (! $this->ok($healthResponse) && $health === null) {
            return $this->row('etcd', $node['name'], $node['ip'], 'down');
        }

        $isLeader = false;
        $raftTerm = null;
        $dbKb = 0;

        if (($status = $this->json($maintResponse)) !== null) {
            $memberId = $status['header']['member_id'] ?? null;
            $leaderId = $status['leader'] ?? null;
            $isLeader = $memberId && $leaderId && $memberId === $leaderId;
            $raftTerm = $status['raftTerm'] ?? null;
            $dbKb = intdiv((int) ($status['dbSizeInUse'] ?? 0), 1024);
        }

        return $this->row('etcd', $node['name'], $node['ip'], $healthy ? 'up' : 'down', metrics: [
            'leader' => $isLeader,
            'raft_term' => $raftTerm,
            'db_kb' => $dbKb,
        ]);
    }

    private function parseHaproxyNode(array $node, Response|Throwable $response): array
    {
        if (! $this->ok($response)) {
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
    }

    private function parseAuthentikNode(array $node, Response|Throwable $response): array
    {
        $ok = $this->ok($response, [200, 204]);

        return $this->row('authentik', $node['name'], $node['ip'], $ok ? 'up' : 'down');
    }

    private function parseNginxStatus(array $node, Response|Throwable $response): array
    {
        if (! $this->ok($response)) {
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
    }

    /**
     * /api/v3/tasks/tasks/status/ — background task health (email, outpost
     * sync, policy cache...). Rejected/errored tasks don't affect
     * /health/ready but cause silent UX failures.
     */
    private function parseAuthentikTaskQueue(string $vip, ?string $token, Response|Throwable|null $response): array
    {
        if (! $token) {
            return $this->row('worker_queue', 'Worker Queue', $vip, 'unknown', message: 'no token configured');
        }
        if ($response === null) {
            return $this->row('worker_queue', 'Worker Queue', $vip, 'down', message: 'request not sent');
        }
        if ($response instanceof Response && in_array($response->status(), [401, 403], true)) {
            return $this->row('worker_queue', 'Worker Queue', $vip, 'down', message: 'unauthorized — token needs superuser permissions');
        }
        if (! $this->ok($response)) {
            $status = $response instanceof Response ? $response->status() : 'connection error';

            return $this->row('worker_queue', 'Worker Queue', $vip, 'down', message: "HTTP {$status}");
        }

        $d = $this->json($response) ?? [];
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
    }

    /**
     * /api/v3/tasks/workers/ — workers currently connected via the broker
     * heartbeat. The only reliable signal a worker is actually consuming
     * tasks (the :9080 liveness probe stays 200 even when the dramatiq
     * consumer is dead). worker_id format is "<uuid>@<hostname>", mapped
     * back to a node so any node with zero connected workers is flagged.
     */
    private function parseAuthentikWorkers(array $nodes, string $vip, ?string $token, Response|Throwable|null $response): array
    {
        $expected = array_map(fn ($n) => $n['name'] ?? $n['ip'], $nodes);

        if (! $token) {
            return $this->row('workers', 'Workers', $vip, 'unknown', message: 'no token configured', metrics: [
                'count' => 0, 'expected' => count($expected), 'present' => [], 'missing' => $expected, 'mismatched' => [],
            ]);
        }
        if ($response === null) {
            return $this->row('workers', 'Workers', $vip, 'down', message: 'request not sent', metrics: [
                'count' => 0, 'expected' => count($expected), 'present' => [], 'missing' => $expected, 'mismatched' => [],
            ]);
        }
        if ($response instanceof Response && in_array($response->status(), [401, 403], true)) {
            return $this->row('workers', 'Workers', $vip, 'down', message: 'unauthorized — token needs admin perms', metrics: [
                'count' => 0, 'expected' => count($expected), 'present' => [], 'missing' => $expected, 'mismatched' => [],
            ]);
        }
        if (! $this->ok($response)) {
            $status = $response instanceof Response ? $response->status() : 'connection error';

            return $this->row('workers', 'Workers', $vip, 'down', message: "HTTP {$status}", metrics: [
                'count' => 0, 'expected' => count($expected), 'present' => [], 'missing' => $expected, 'mismatched' => [],
            ]);
        }

        $workers = $this->json($response) ?? [];
        $workers = is_array($workers) ? $workers : [];
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
