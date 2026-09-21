<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NetworkPollRun extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
        ];
    }

    public function devices(): HasMany
    {
        return $this->hasMany(NetworkDevice::class, 'last_poll_run_id');
    }

    /**
     * How many of the devices this run dispatched jobs for have actually
     * reported back (ok or error) tagged with this run's id - a count equal
     * to device_count means the run is fully accounted for; less means some
     * jobs are still queued/running, or got stuck/lost.
     */
    public function reportedCount(): int
    {
        return $this->devices()->count();
    }
}
