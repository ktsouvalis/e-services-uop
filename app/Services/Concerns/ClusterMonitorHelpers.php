<?php

namespace App\Services\Concerns;

/**
 * Shared between Pangolin's and Authentik's ClusterMonitor — both are
 * single-node, native-PHP, admin-settings-driven pollers with an identical
 * row shape (see each class's own docblock for what it currently checks).
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
}
