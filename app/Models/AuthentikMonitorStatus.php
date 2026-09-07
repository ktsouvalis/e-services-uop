<?php

namespace App\Models;

use App\Models\Concerns\HasMonitorMetricsSummary;
use Illuminate\Database\Eloquent\Model;

class AuthentikMonitorStatus extends Model
{
    use HasMonitorMetricsSummary;

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

    private function summarizeExtraMetrics(array $m): ?string
    {
        return match ($this->service) {
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
