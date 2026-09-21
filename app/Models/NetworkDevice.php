<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NetworkDevice extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'last_polled_at' => 'datetime',
        ];
    }

    public function macHistory(): HasMany
    {
        return $this->hasMany(NetworkMacHistory::class);
    }

    public function ports(): HasMany
    {
        return $this->hasMany(NetworkDevicePort::class);
    }

    public function pollRun(): BelongsTo
    {
        return $this->belongsTo(NetworkPollRun::class, 'last_poll_run_id');
    }
}
