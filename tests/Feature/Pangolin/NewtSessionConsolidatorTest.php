<?php

use App\Models\PangolinNewtAgent;
use App\Models\PangolinNewtConnection;
use App\Services\Pangolin\NewtSessionConsolidator;
use Carbon\Carbon;

beforeEach(function () {
    $this->consolidator = new NewtSessionConsolidator();
    $this->agent = PangolinNewtAgent::factory()->create(['name' => 'patra']);
});

/**
 * One raw flow from Kostas to 10.23.2.50:22, starting $startOffset seconds
 * after a fixed base time and lasting $duration seconds (null = still open).
 */
function newtFlow(int $startOffset, ?int $duration = 1, array $overrides = []): PangolinNewtConnection
{
    $base = Carbon::parse('2026-09-28 05:00:00', 'UTC');
    $agent = test()->agent;

    return PangolinNewtConnection::factory()->create([
        'newt_agent_id' => $agent->id, 'agent_name' => $agent->name, 'agent_ip' => $agent->ip,
        'user_name' => 'Kostas Tsouvalis', 'user_email' => 'ktsouvalis@uop.gr',
        'src_ip' => '100.90.0.5', 'dst_ip' => '10.23.2.50', 'dst_port' => '22', 'proto' => 'tcp',
        'resource_id' => 35, 'site_name' => 'Patras',
        'started_at' => $base->copy()->addSeconds($startOffset),
        'ended_at' => $duration === null ? null : $base->copy()->addSeconds($startOffset + $duration),
        ...$overrides,
    ]);
}

test('a burst of 15 short successful connections within 5s gaps collapses to one session', function () {
    // Each lasts 2s and the next starts 4s after the previous one ended.
    $flows = collect(range(0, 14))->map(fn ($i) => newtFlow($i * 6, 2));

    $sessions = $this->consolidator->consolidate($flows);

    expect($sessions)->toHaveCount(1);
    expect($sessions[0]->connection_count)->toBe(15);
    expect($sessions[0]->failed)->toBeFalse();
    expect($sessions[0]->started_at->equalTo($flows->first()->started_at))->toBeTrue();
    expect($sessions[0]->ended_at->equalTo($flows->last()->ended_at))->toBeTrue();
    expect($sessions[0]->statusLabel())->toBe('OK');
});

test('a gap longer than 5s between one flow ending and the next starting splits the session', function () {
    $flows = collect([newtFlow(0, 2), newtFlow(6, 2), newtFlow(14, 2)]); // gaps: 4s, 6s

    $sessions = $this->consolidator->consolidate($flows);

    expect($sessions)->toHaveCount(2);
    // Newest first, like the tab has always listed.
    expect($sessions[0]->connection_count)->toBe(1);
    expect($sessions[1]->connection_count)->toBe(2);
});

test('the gap is measured from the latest end so far, not just the previous flow\'s end', function () {
    // A long flow (0–100s) overlaps a short one (10–11s); a flow starting at
    // 103s is within 5s of the long one's end and must still merge.
    $flows = collect([newtFlow(0, 100), newtFlow(10, 1), newtFlow(103, 1)]);

    expect($this->consolidator->consolidate($flows))->toHaveCount(1);
});

test('the session gap follows config', function () {
    config(['pangolin.session_gap_seconds' => 10]);
    $flows = collect([newtFlow(0, 2), newtFlow(10, 2)]); // 8s gap

    expect($this->consolidator->consolidate($flows))->toHaveCount(1);
});

test('30 failed attempts 20s apart collapse to one row', function () {
    $flows = collect(range(0, 29))->map(fn ($i) => newtFlow($i * 20, 0, [
        'failed' => true, 'failure_reason' => $i === 0 ? 'refused' : 'timeout', 'failure_detail' => 'dial tcp: i/o timeout',
    ]));

    $sessions = $this->consolidator->consolidate($flows);

    expect($sessions)->toHaveCount(1);
    expect($sessions[0]->connection_count)->toBe(30);
    expect($sessions[0]->failed)->toBeTrue();
    expect($sessions[0]->failure_reason)->toBe('timeout'); // dominant reason
    expect($sessions[0]->statusLabel())->toBe('Failed: timeout');
    // 05:00 UTC start, last attempt at 05:09:40 UTC — shown in Athens time.
    expect($sessions[0]->durationLabel())->toBe('30 attempts, 08:00–08:09');
});

test('failed attempts further apart than the rollup gap split', function () {
    $failed = ['failed' => true, 'failure_reason' => 'timeout'];
    $flows = collect([newtFlow(0, 5, $failed), newtFlow(50, 5, $failed), newtFlow(200, 5, $failed)]);

    $sessions = $this->consolidator->consolidate($flows);

    expect($sessions->pluck('connection_count')->all())->toBe([1, 2]);
    expect($sessions[0]->durationLabel())->toBe('1 attempt, 08:03');
});

test('failed and successful connections to the same destination never merge', function () {
    $flows = collect([
        newtFlow(0, 1),
        newtFlow(2, 0, ['failed' => true, 'failure_reason' => 'refused']),
        newtFlow(3, 1),
    ]);

    $sessions = $this->consolidator->consolidate($flows);

    expect($sessions)->toHaveCount(2);
    $ok = $sessions->firstWhere('failed', false);
    $failed = $sessions->firstWhere('failed', true);
    expect($ok->connection_count)->toBe(2);
    expect($failed->connection_count)->toBe(1);
});

test('different destinations, users or resources never merge', function () {
    $flows = collect([
        newtFlow(0, 1),
        newtFlow(1, 1, ['dst_port' => '3389']),
        newtFlow(2, 1, ['user_name' => 'Someone Else', 'user_email' => 'someone@uop.gr']),
        newtFlow(3, 1, ['resource_id' => 36]),
    ]);

    expect($this->consolidator->consolidate($flows))->toHaveCount(4);
});

test('flows through different sites merge into one session that lists every site', function () {
    $flows = collect([
        newtFlow(0, 1, ['site_name' => 'Tripoli']),
        newtFlow(2, 1, ['site_name' => 'Patras']),
        newtFlow(4, 1, ['site_name' => 'Kalamata']),
        newtFlow(6, 1, ['site_name' => 'Patras']),
    ]);

    $sessions = $this->consolidator->consolidate($flows);

    expect($sessions)->toHaveCount(1);
    expect($sessions[0]->sites())->toBe(['Kalamata', 'Patras', 'Tripoli']);
});

test('an open session is kept open, using its start as the gap reference', function () {
    $flows = collect([newtFlow(0, 2), newtFlow(4, null), newtFlow(8, 1)]);

    $sessions = $this->consolidator->consolidate($flows);

    expect($sessions)->toHaveCount(1);
    expect($sessions[0]->connection_count)->toBe(3);
    expect($sessions[0]->ended_at)->toBeNull();
    expect($sessions[0]->durationLabel())->toBe('ongoing');
});

test('a lone open session survives consolidation', function () {
    $sessions = $this->consolidator->consolidate(collect([newtFlow(0, null)]));

    expect($sessions)->toHaveCount(1);
    expect($sessions[0]->ended_at)->toBeNull();
});

test('asRawFlows returns one row per stored flow, newest first', function () {
    $flows = collect(range(0, 4))->map(fn ($i) => newtFlow($i, 0));

    $rows = $this->consolidator->asRawFlows($flows);

    expect($rows)->toHaveCount(5);
    expect($rows->pluck('id')->all())->toBe($flows->pluck('id')->reverse()->values()->all());
    expect($rows->every(fn ($r) => $r->connection_count === 1))->toBeTrue();
});
