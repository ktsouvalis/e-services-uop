<?php

namespace App\Services\Pangolin;

use App\Models\PangolinNewtConnection;
use Illuminate\Support\Collection;

/**
 * Read-time merge of raw Newt flows (pangolin_newt_connections rows, one per
 * ACCESS START/END pair) into logical sessions for the Connections tab and
 * its export. Storage stays raw so this is reversible (the tab's "raw flows"
 * toggle skips it via asRawFlows()).
 *
 * Grouping key: (user email/name, else src IP) + dst ip:port + proto +
 * resource_id + failed. Site is deliberately NOT in the key — one user's
 * flows to one resource routinely alternate between the Patras/Tripoli/
 * Kalamata agents — so each logical row lists its sites instead. Failed and
 * successful flows never merge with each other.
 *
 * - Successful flows merge when next.started_at − max(ended_at so far)
 *   <= config('pangolin.session_gap_seconds') (5s — mirrors Newt's own
 *   consolidateSessions(), which it only applies to the batch it ships to
 *   Pangolin). An open flow (ended_at null) uses its started_at as the gap
 *   reference, and leaves the merged session open.
 * - Failed attempts merge when the gap between consecutive starts
 *   <= config('pangolin.failure_rollup_gap_seconds') (60s) — so a client's
 *   reconnect loop (e.g. one attempt every ~20s for hours) becomes one row.
 */
class NewtSessionConsolidator
{
    /**
     * @param  iterable<int, PangolinNewtConnection>  $connections
     * @return Collection<int, NewtLogicalSession> newest first
     */
    public function consolidate(iterable $connections): Collection
    {
        $sessionGap = (int) config('pangolin.session_gap_seconds', 5);
        $failureGap = (int) config('pangolin.failure_rollup_gap_seconds', 60);

        return collect($connections)
            ->groupBy(fn (PangolinNewtConnection $c) => $this->groupKey($c))
            ->flatMap(function (Collection $group) use ($sessionGap, $failureGap) {
                $sorted = $group->sortBy([
                    fn ($a, $b) => $a->started_at <=> $b->started_at,
                    fn ($a, $b) => $a->id <=> $b->id,
                ])->values();

                return $sorted->first()->failed
                    ? $this->mergeFailed($sorted, $failureGap)
                    : $this->mergeSuccessful($sorted, $sessionGap);
            })
            ->pipe(fn (Collection $sessions) => $this->newestFirst($sessions));
    }

    /**
     * One logical row per raw flow — the "raw flows" debugging view.
     *
     * @param  iterable<int, PangolinNewtConnection>  $connections
     * @return Collection<int, NewtLogicalSession>
     */
    public function asRawFlows(iterable $connections): Collection
    {
        return $this->newestFirst(
            collect($connections)->map(fn ($c) => new NewtLogicalSession(collect([$c]))),
        );
    }

    private function groupKey(PangolinNewtConnection $c): string
    {
        $who = strtolower($c->user_email ?: ($c->user_name ?: $c->src_ip));

        return implode('|', [
            $who, "{$c->dst_ip}:{$c->dst_port}", strtolower($c->proto), $c->resource_id, $c->failed ? 1 : 0,
        ]);
    }

    /** @return array<int, NewtLogicalSession> */
    private function mergeSuccessful(Collection $sorted, int $gap): array
    {
        $sessions = [];
        $cluster = [];
        $reference = null;

        foreach ($sorted as $c) {
            $start = $c->started_at->getTimestamp();
            if ($cluster && $start - $reference > $gap) {
                $sessions[] = new NewtLogicalSession(collect($cluster));
                $cluster = [];
            }
            $end = ($c->ended_at ?? $c->started_at)->getTimestamp();
            $reference = $cluster ? max($reference, $end) : $end;
            $cluster[] = $c;
        }
        if ($cluster) {
            $sessions[] = new NewtLogicalSession(collect($cluster));
        }

        return $sessions;
    }

    /** @return array<int, NewtLogicalSession> */
    private function mergeFailed(Collection $sorted, int $gap): array
    {
        $sessions = [];
        $cluster = [];
        $previousStart = null;

        foreach ($sorted as $c) {
            $start = $c->started_at->getTimestamp();
            if ($cluster && $start - $previousStart > $gap) {
                $sessions[] = new NewtLogicalSession(collect($cluster));
                $cluster = [];
            }
            $previousStart = $start;
            $cluster[] = $c;
        }
        if ($cluster) {
            $sessions[] = new NewtLogicalSession(collect($cluster));
        }

        return $sessions;
    }

    /**
     * Same order the tab always used (started_at desc, id desc as the
     * tiebreaker — started_at alone isn't unique).
     *
     * @param  Collection<int, NewtLogicalSession>  $sessions
     */
    private function newestFirst(Collection $sessions): Collection
    {
        return $sessions->sortBy([
            fn ($a, $b) => $b->started_at <=> $a->started_at,
            fn ($a, $b) => $b->id <=> $a->id,
        ])->values();
    }
}
