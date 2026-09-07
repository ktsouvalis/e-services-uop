<?php

use App\Jobs\Pangolin\PollCluster;
use App\Models\PangolinMonitorStatus;
use Illuminate\Support\Facades\Http;

function pangolinHaproxyCsv(array $pools): string
{
    $lines = ['#pxname,svname,f2,f3,f4,f5,f6,f7,f8,f9,f10,f11,f12,f13,f14,f15,f16,status'];
    $filler = implode(',', array_fill(0, 15, '0'));

    foreach ($pools as $pool => $statuses) {
        foreach ($statuses as $i => $status) {
            $lines[] = "{$pool},srv{$i},{$filler},{$status}";
        }
    }

    return implode("\n", $lines);
}

function fakePangolinClusterHttp(string $haproxyCsv): void
{
    Http::fake([
        'http://10.20.30.1:9000/stats;csv' => Http::response($haproxyCsv, 200),
        'http://10.20.30.1:8008/' => Http::response(['role' => 'primary'], 200),
        'http://10.20.30.1:2379/health' => Http::response(['health' => true], 200),
        'http://10.20.30.1:2379/v3/maintenance/status' => Http::response([
            'header' => ['member_id' => 1], 'leader' => 1, 'raftTerm' => 3, 'dbSizeInUse' => 4096,
        ], 200),
        '*' => Http::response('', 200),
    ]);

    config([
        'pangolin.nodes' => [
            ['ip' => '10.20.30.1', 'name' => 'node-1', 'base_priority' => 100],
        ],
        'pangolin.newt.hosts' => [],
        'pangolin.vip' => null,
    ]);
}

test('a backend pool with zero UP servers is degraded, but a pool with at least one UP server is not', function () {
    fakePangolinClusterHttp(pangolinHaproxyCsv([
        'pangolin_primary' => ['UP', 'DOWN', 'DOWN'],
        'pangolin_replica' => ['DOWN', 'DOWN', 'DOWN'],
    ]));

    PollCluster::dispatch();

    $haproxy = PangolinMonitorStatus::where('service', 'haproxy')->where('node_ip', '10.20.30.1')->first();
    expect($haproxy->status)->toBe('degraded');
});

test('every pool having at least one UP server is reported up, even with only 1-of-3 UP (Patroni steady state)', function () {
    fakePangolinClusterHttp(pangolinHaproxyCsv([
        'pangolin_primary' => ['UP', 'DOWN', 'DOWN'],
        'pangolin_replica' => ['DOWN', 'UP', 'DOWN'],
    ]));

    PollCluster::dispatch();

    $haproxy = PangolinMonitorStatus::where('service', 'haproxy')->where('node_ip', '10.20.30.1')->first();
    expect($haproxy->status)->toBe('up');
});

test('polling twice upserts the same row per service+node_ip instead of accumulating duplicates', function () {
    config([
        'pangolin.nodes' => [
            ['ip' => '10.20.30.1', 'name' => 'node-1', 'base_priority' => 100],
        ],
        'pangolin.newt.hosts' => [],
        'pangolin.vip' => null,
    ]);

    // A second Http::fake() call merges rather than replaces stubs (the
    // first-registered match for a URL always wins), so switching the
    // haproxy response between the two polls has to go through a sequence
    // on a single fake() call rather than re-faking in between.
    Http::fake([
        'http://10.20.30.1:9000/stats;csv' => Http::sequence()
            ->push(pangolinHaproxyCsv(['pool' => ['UP']]), 200)
            ->push(pangolinHaproxyCsv(['pool' => ['DOWN']]), 200),
        'http://10.20.30.1:8008/' => Http::response(['role' => 'primary'], 200),
        'http://10.20.30.1:2379/health' => Http::response(['health' => true], 200),
        'http://10.20.30.1:2379/v3/maintenance/status' => Http::response([
            'header' => ['member_id' => 1], 'leader' => 1, 'raftTerm' => 3, 'dbSizeInUse' => 4096,
        ], 200),
        '*' => Http::response('', 200),
    ]);

    PollCluster::dispatch();
    $firstCount = PangolinMonitorStatus::count();

    PollCluster::dispatch();

    expect(PangolinMonitorStatus::count())->toBe($firstCount);
    $haproxy = PangolinMonitorStatus::where('service', 'haproxy')->where('node_ip', '10.20.30.1')->first();
    expect($haproxy->status)->toBe('degraded');
});
