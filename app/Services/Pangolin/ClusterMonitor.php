<?php

namespace App\Services\Pangolin;

use App\Services\Concerns\ClusterMonitorHelpers;
use Illuminate\Support\Facades\Http;
use phpseclib3\Net\SSH2;
use Throwable;

/**
 * Native PHP port of pangolin-utils' monitor.py check_* functions.
 * monitor.py itself is a Textual TUI with no headless/JSON mode, so this
 * re-implements its HTTP/SSH checks directly rather than shelling out.
 */
class ClusterMonitor
{
    use ClusterMonitorHelpers;

    private int $timeout;

    public function __construct()
    {
        $this->timeout = (int) config('pangolin.http_timeout', 5);
    }

    /**
     * Run every check and return a flat list of rows shaped like
     * PangolinMonitorStatus columns (service, node_name, node_ip, status, role, metrics, message).
     */
    public function run(): array
    {
        $rows = [];
        $nodes = config('pangolin.nodes', []);

        foreach ($nodes as $node) {
            $rows[] = $this->checkPangolinNode($node);
            $rows[] = $this->checkGerbilNode($node);
            $rows[] = $this->checkKeepalivedNode($node);
            $rows[] = $this->checkPatroniNode($node);
            $rows[] = $this->checkEtcdNode($node);
            $rows[] = $this->checkHaproxyNode($node);
        }

        foreach (config('pangolin.newt.hosts', []) as $host) {
            $rows[] = $this->checkNewtNode($host);
        }

        if ($vip = config('pangolin.vip')) {
            $rows[] = $this->checkVipHolder($vip);
        }

        return $rows;
    }

    private function httpOk(string $url): bool
    {
        try {
            $status = Http::timeout($this->timeout)->get($url)->status();

            return in_array($status, [200, 401, 403], true);
        } catch (Throwable) {
            return false;
        }
    }

    private function checkVipHolder(string $vip): array
    {
        $port = config('pangolin.ports.pangolin');
        $reachable = $this->httpOk("http://{$vip}:{$port}/api/v1/");

        return $this->row('vip', 'VIP', $vip, $reachable ? 'up' : 'down');
    }

    private function checkKeepalivedNode(array $node): array
    {
        $port = config('pangolin.ports.pangolin');
        $pangolinUp = $this->httpOk("http://{$node['ip']}:{$port}/api/v1/");
        $base = (int) ($node['base_priority'] ?? 100);
        $trackWeight = (int) config('pangolin.keepalived.track_weight', -25);
        $effective = $pangolinUp ? $base : $base + $trackWeight;

        return $this->row('keepalived', $node['name'], $node['ip'], $pangolinUp ? 'up' : 'degraded', metrics: [
            'base_priority' => $base,
            'effective_priority' => $effective,
        ]);
    }

    private function checkPangolinNode(array $node): array
    {
        $port = config('pangolin.ports.pangolin');
        $ok = $this->httpOk("http://{$node['ip']}:{$port}/api/v1/");

        return $this->row('pangolin', $node['name'], $node['ip'], $ok ? 'up' : 'down');
    }

    private function checkGerbilNode(array $node): array
    {
        // No HTTP health endpoint of its own — inferred from Pangolin (shared compose stack).
        $port = config('pangolin.ports.pangolin');
        $ok = $this->httpOk("http://{$node['ip']}:{$port}/api/v1/");

        return $this->row('gerbil', $node['name'], $node['ip'], $ok ? 'up' : 'down', message: 'inferred from pangolin API');
    }

    private function checkPatroniNode(array $node): array
    {
        $port = config('pangolin.ports.patroni');

        try {
            $data = Http::timeout($this->timeout)->get("http://{$node['ip']}:{$port}/")->json();
            $rawRole = $data['role'] ?? 'unknown';
            $isLeader = in_array($rawRole, ['primary', 'master', 'standby_leader'], true);
            $role = $isLeader ? 'primary' : 'replica';

            $lagBytes = null;
            if (! $isLeader) {
                $received = $data['xlog']['received_location'] ?? null;
                $replayed = $data['xlog']['replayed_location'] ?? null;
                if ($received !== null && $replayed !== null) {
                    $lagBytes = max(0, $received - $replayed);
                }
            }

            return $this->row('patroni', $node['name'], $node['ip'], 'up', role: $role, metrics: [
                'state' => $data['replication_state'] ?? ($data['state'] ?? 'unknown'),
                'timeline' => $data['timeline'] ?? null,
                'pending_restart' => $data['pending_restart'] ?? false,
                'lag_bytes' => $lagBytes,
            ]);
        } catch (Throwable) {
            return $this->row('patroni', $node['name'], $node['ip'], 'down', role: 'down', message: 'unreachable');
        }
    }

    private function checkEtcdNode(array $node): array
    {
        $port = config('pangolin.ports.etcd');

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
            // etcd's v3 JSON gateway wants a JSON *object* body (even though this
            // request type has no fields) — Http::post($url, []) serializes an
            // empty PHP array as `[]`, which etcd's Go server rejects with
            // "cannot unmarshal array into Go value of type map[string]json.RawMessage".
            // withBody('{}', ...) forces an actual empty object.
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
        $port = config('pangolin.ports.haproxy_stats');

        try {
            $response = Http::timeout($this->timeout)->get("http://{$node['ip']}:{$port}/stats;csv");
            if (! $response->ok()) {
                return $this->row('haproxy', $node['name'], $node['ip'], 'down');
            }

            $parsed = $this->parseHaproxyStats($response->body());

            return $this->row('haproxy', $node['name'], $node['ip'], $parsed['status'], metrics: [
                'backends' => $parsed['backends'],
            ]);
        } catch (Throwable) {
            return $this->row('haproxy', $node['name'], $node['ip'], 'down');
        }
    }

    private function checkNewtNode(array $host): array
    {
        $username = config('pangolin.newt.ssh.username') ?: config('pangolin.ssh.username');
        $keyPath = config('pangolin.newt.ssh.key_path') ?: config('pangolin.ssh.key_path');

        try {
            $ssh = new SSH2($host['ip'], 22, $this->timeout);
            $key = \phpseclib3\Crypt\PublicKeyLoader::load(file_get_contents($keyPath));
            $authenticated = $ssh->login($username, $key);
            $ssh->disconnect();

            return $this->row('newt', $host['name'], $host['ip'], $authenticated ? 'up' : 'down');
        } catch (Throwable $e) {
            return $this->row('newt', $host['name'], $host['ip'], 'down', message: substr($e->getMessage(), 0, 120));
        }
    }
}
