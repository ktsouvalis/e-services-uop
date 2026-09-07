<?php

namespace App\Models\Concerns;

/**
 * Shared between PangolinMonitorStatus and AuthentikMonitorStatus — both
 * carry an identical `metrics` shape for the services their monitors have in
 * common (keepalived/patroni/etcd/haproxy). Authentik's monitor covers a few
 * extra services (nginx/workers/worker_queue) with no Pangolin equivalent —
 * models using this trait can override summarizeExtraMetrics() for those.
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

        return $this->summarizeCommonMetrics($m) ?? $this->summarizeExtraMetrics($m);
    }

    private function summarizeCommonMetrics(array $m): ?string
    {
        return match ($this->service) {
            'keepalived' => "priority: {$m['effective_priority']}/{$m['base_priority']}",
            'patroni' => collect([
                $m['state'] ?? null,
                isset($m['lag_bytes']) ? "lag: {$m['lag_bytes']}B" : null,
                ($m['pending_restart'] ?? false) ? 'pending restart' : null,
            ])->filter()->implode(', '),
            'etcd' => collect([
                ($m['leader'] ?? false) ? 'leader' : null,
                isset($m['raft_term']) ? "term: {$m['raft_term']}" : null,
                isset($m['db_kb']) ? "db: {$m['db_kb']}KB" : null,
            ])->filter()->implode(', '),
            'haproxy' => collect($m['backends'] ?? [])
                ->map(function ($servers, $pool) {
                    $up = collect($servers)->where('status', 'UP')->count();

                    return "{$pool}: {$up}/".count($servers);
                })->implode(', '),
            default => null,
        } ?: null;
    }

    private function summarizeExtraMetrics(array $m): ?string
    {
        return null;
    }
}
