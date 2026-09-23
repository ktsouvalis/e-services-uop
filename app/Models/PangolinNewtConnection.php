<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One resolved Newt ACCESS session (a connection a user attempted/made to a
 * Pangolin resource), upserted by App\Services\Pangolin\NewtConnectionSync
 * keyed on (newt_agent_id, session_id) — see that class and the migration
 * for the full shape. ended_at null means the session was still open the
 * last time it was fetched.
 */
class PangolinNewtConnection extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(PangolinNewtAgent::class, 'newt_agent_id');
    }

    /**
     * started_at/ended_at are stored and queried in UTC (this app's own
     * timezone — config('app.timezone')), matching Newt's own log
     * timestamps (which carry an explicit "Z"/UTC suffix). This app's users
     * are in Athens (UTC+3 in September), so the Connections tab displays
     * this converted copy rather than the raw stored value — see
     * PangolinController::filteredConnections()'s from/to handling for the
     * matching conversion on the filter side.
     */
    protected function startedAtLocal(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->started_at?->copy()->timezone('Europe/Athens'),
        );
    }
}
