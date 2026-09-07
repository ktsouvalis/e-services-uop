<?php

namespace App\Services\Concerns;

/**
 * Shared between Pangolin's and Authentik's ClusterMonitor — both are native
 * PHP ports of their respective monitor.py, with an identical row shape and
 * identical HAProxy stats-CSV parsing/pool-health logic.
 */
trait ClusterMonitorHelpers
{
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

    /**
     * Parse HAProxy's `/stats;csv` body into a per-pool backend list and
     * decide overall status. A backend pool is only unhealthy when it has
     * zero UP servers — partial UP counts within a pool are role-based and
     * expected (e.g. exactly 1 UP in a Patroni primary pool) — mirrors
     * monitor.py's HAProxyPanel "any_zero" logic exactly.
     */
    private function parseHaproxyStats(string $csvBody): array
    {
        $backends = [];

        foreach (explode("\n", $csvBody) as $line) {
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

        $anyPoolFullyDown = false;
        foreach ($backends as $servers) {
            $ups = count(array_filter($servers, fn ($s) => $s['status'] === 'UP'));
            if ($ups === 0) {
                $anyPoolFullyDown = true;
                break;
            }
        }

        return ['backends' => $backends, 'status' => $anyPoolFullyDown ? 'degraded' : 'up'];
    }
}
