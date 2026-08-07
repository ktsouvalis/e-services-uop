<?php

namespace App\Models;

use App\Models\Concerns\HasMonitorMetricsSummary;
use Illuminate\Database\Eloquent\Model;

class PangolinMonitorStatus extends Model
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
}
