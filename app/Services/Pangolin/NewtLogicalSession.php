<?php

namespace App\Services\Pangolin;

use App\Models\PangolinNewtConnection;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * One row of the Connections tab: one or more raw Newt flows
 * (PangolinNewtConnection rows) merged by NewtSessionConsolidator, or a
 * single raw flow when the "raw flows" toggle bypasses consolidation. The
 * display helpers (who(), durationLabel(), statusLabel(), ...) are shared by
 * the Blade view and the xlsx/ods export so both render identically.
 */
final class NewtLogicalSession
{
    /** @param  Collection<int, PangolinNewtConnection>  $members  sorted by started_at asc */
    public function __construct(public readonly Collection $members)
    {
    }

    public function first(): PangolinNewtConnection
    {
        return $this->members->first();
    }

    public function __get(string $name): mixed
    {
        return match ($name) {
            // Most recent flow's id — stable, and equal to the row's own id
            // for a single-flow session.
            'id' => $this->members->max('id'),
            'started_at' => $this->first()->started_at,
            'started_at_local' => $this->first()->started_at_local,
            'ended_at' => $this->endedAt(),
            'connection_count' => $this->members->count(),
            'failed' => (bool) $this->first()->failed,
            'failure_reason' => $this->dominantFailureReason(),
            // Identity columns can be null on some flows (Postgres lookup
            // failed that fetch) — take the first one that resolved.
            'user_name', 'user_email', 'client_name', 'resource_name' => $this->members->pluck($name)->filter()->first(),
            default => $this->first()->{$name},
        };
    }

    // data_get()/pluck() probe objects with isset() before reading.
    public function __isset(string $name): bool
    {
        return $this->__get($name) !== null;
    }

    /** null while any merged flow is still open. */
    public function endedAt(): ?Carbon
    {
        if ($this->members->contains(fn ($m) => $m->ended_at === null)) {
            return null;
        }

        return $this->members->max('ended_at');
    }

    /** Most common failure_reason among the merged flows (null when not failed). */
    public function dominantFailureReason(): ?string
    {
        return $this->members->pluck('failure_reason')->filter()->countBy()->sortDesc()->keys()->first();
    }

    /** @return array<int, string> distinct sites (Newt agents) the flows went through */
    public function sites(): array
    {
        return $this->members
            ->map(fn ($m) => $m->site_name ?: "site#{$m->resource_id}")
            ->unique()->sort()->values()->all();
    }

    public function who(): string
    {
        return $this->user_name ?: ($this->user_email ?: $this->src_ip);
    }

    public function destination(): string
    {
        return "{$this->dst_ip}:{$this->dst_port}";
    }

    public function durationLabel(): string
    {
        if ($this->failed) {
            $count = $this->connection_count;
            $from = $this->started_at_local->format('H:i');
            $last = $this->members->map(fn ($m) => $m->ended_at ?? $m->started_at)->max()
                ->copy()->timezone('Europe/Athens')->format('H:i');
            $noun = $count === 1 ? 'attempt' : 'attempts';

            return $from === $last ? "{$count} {$noun}, {$from}" : "{$count} {$noun}, {$from}–{$last}";
        }

        $ended = $this->endedAt();

        return $ended ? $this->started_at->diffForHumans($ended, true) : 'ongoing';
    }

    public function statusLabel(): string
    {
        return $this->failed ? 'Failed: '.($this->failure_reason ?? 'other') : 'OK';
    }
}
