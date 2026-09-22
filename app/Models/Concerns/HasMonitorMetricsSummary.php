<?php

namespace App\Models\Concerns;

/**
 * Shared between PangolinMonitorStatus and AuthentikMonitorStatus — both
 * single-node monitors (see each service's ClusterMonitor). Each model
 * overrides summarizeExtraMetrics() for its own services (Pangolin: api;
 * Authentik: nginx/workers/worker_queue).
 */
trait HasMonitorMetricsSummary
{
    /**
     * Short, human-readable summary of the collected metrics for this row's
     * service — the raw JSON in `metrics` is otherwise invisible in the UI.
     */
    public function getMetricsSummaryAttribute(): ?string
    {
        $m = $this->metrics;
        if (! $m) {
            return null;
        }

        return $this->summarizeExtraMetrics($m);
    }

    private function summarizeExtraMetrics(array $m): ?string
    {
        return null;
    }
}
