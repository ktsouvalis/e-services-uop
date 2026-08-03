<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuthentikMonitorStatus extends Model
{
    protected $guarded = ['id'];

    // Included in JSON (the monitor tab's polling endpoint) as well as Blade.
    protected $appends = ['metrics_summary'];

    protected function casts(): array
    {
        return [
            'metrics' => 'array',
            'checked_at' => 'datetime',
        ];
    }

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
            'nginx' => "active: {$m['active']} (R={$m['reading']} W={$m['writing']} Wait={$m['waiting']})",
            'workers' => "{$m['count']}/{$m['expected']} connected",
            'worker_queue' => collect([
                "running: {$m['running']}",
                "queued: {$m['queued']}",
                ($m['rejected'] ?? 0) ? "rejected: {$m['rejected']}" : null,
                ($m['error'] ?? 0) ? "error: {$m['error']}" : null,
            ])->filter()->implode(', '),
            default => null,
        } ?: null;
    }
}
