<?php

namespace App\Services\Pangolin;

use App\Models\PangolinNewtAgent;

/**
 * Ported from logs_viewer.py's get_nodes()/get_ssh_creds()/load_services()/
 * build_node_map(). Returns an ordered map: node name -> {ip, ssh: [user,
 * key_path], services: [[label, type, identifier], ...]}.
 *
 * Every "nodes" group except Newt maps to the same real multi-node HA
 * topology (config('pangolin.nodes')) — that config is otherwise only read
 * by the Monitor tab's now-retired 3-node checks, but Logs genuinely does
 * still need it: it fetches from every physical cluster node, which is a
 * separate concern from what the (now single-node) Monitor tab checks. See
 * CLAUDE.md's Pangolin module section. Newt agents come from the DB
 * (App\Models\PangolinNewtAgent — admin-managed, arbitrary count), the same
 * source ClusterMonitor's Newt checks and ConfigYamlWriter already use.
 */
class LogsNodeMap
{
    /**
     * @return array<string, array{ip: string, ssh: array{0: string, 1: string}, services: array<int, array{0: string, 1: string, 2: string}>}>
     */
    public function build(): array
    {
        $nodeMap = [];

        foreach (config('pangolin.services', []) as $svc) {
            $label = $svc['label'];
            $nodesKey = $svc['nodes'];
            $type = $svc['type'];
            $identifier = $svc['container'] ?? $svc['unit'] ?? '';

            $creds = $this->sshCreds($nodesKey);
            foreach ($this->nodesFor($nodesKey) as $node) {
                $name = $node['name'];
                if (! isset($nodeMap[$name])) {
                    $nodeMap[$name] = ['ip' => $node['ip'], 'ssh' => $creds, 'services' => []];
                }
                $nodeMap[$name]['services'][] = [$label, $type, $identifier];
            }
        }

        return $nodeMap;
    }

    /** @return array<int, array{ip: string, name: string}> */
    private function nodesFor(string $nodesKey): array
    {
        if ($nodesKey === 'newt') {
            return PangolinNewtAgent::all()->map(fn ($a) => ['ip' => $a->ip, 'name' => $a->name])->all();
        }

        // keepalived and every other real cluster-service group (pangolin/
        // patroni/etcd/haproxy) all share the same 3-node HA topology.
        return config('pangolin.nodes', []);
    }

    /** @return array{0: string, 1: string} [username, key_path] */
    private function sshCreds(string $nodesKey): array
    {
        if ($nodesKey === 'newt') {
            return [
                config('pangolin.newt.ssh.username') ?: config('pangolin.ssh.username'),
                config('pangolin.newt.ssh.key_path') ?: config('pangolin.ssh.key_path'),
            ];
        }

        return [config('pangolin.ssh.username'), config('pangolin.ssh.key_path')];
    }
}
