<?php

use App\Jobs\Authentik\PollCluster;
use App\Models\AuthentikMonitorStatus;
use Illuminate\Support\Facades\Http;

function authentikHaproxyCsv(array $pools): string
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

function fakeAuthentikClusterHttp(string $haproxyCsv): void
{
    Http::fake([
        'http://10.40.50.1:9000/stats;csv' => Http::response($haproxyCsv, 200),
        'http://10.40.50.1:8008/history' => Http::response([], 200),
        'http://10.40.50.1:8008/' => Http::response(['role' => 'primary', 'state' => 'running'], 200),
        'http://10.40.50.1:2379/health' => Http::response(['health' => true], 200),
        'http://10.40.50.1:2379/v3/maintenance/status' => Http::response([
            'header' => ['member_id' => 1], 'leader' => 1, 'raftTerm' => 3, 'dbSizeInUse' => 4096,
        ], 200),
        'https://10.40.50.1/monitor' => Http::response('', 200),
        'https://10.40.50.1:9443/-/health/live/' => Http::response('', 200),
        'http://10.40.50.1:8080/nginx_status' => Http::response("Active connections: 1 \nReading: 0 Writing: 1 Waiting: 0\n", 200),
        '*' => Http::response('', 200),
    ]);

    config([
        'authentik.nodes' => [
            ['ip' => '10.40.50.1', 'name' => 'ak-1', 'base_priority' => 100],
        ],
        'authentik.vip' => null,
        'authentik.credentials.authentik_api_token' => null,
    ]);
}

test('a backend pool with zero UP servers is degraded, but a pool with at least one UP server is not', function () {
    fakeAuthentikClusterHttp(authentikHaproxyCsv([
        'auth_primary' => ['UP', 'DOWN', 'DOWN'],
        'auth_replica' => ['DOWN', 'DOWN', 'DOWN'],
    ]));

    PollCluster::dispatch();

    $haproxy = AuthentikMonitorStatus::where('service', 'haproxy')->where('node_ip', '10.40.50.1')->first();
    expect($haproxy->status)->toBe('degraded');
});

test('every pool having at least one UP server is reported up, even with only 1-of-3 UP (Patroni steady state)', function () {
    fakeAuthentikClusterHttp(authentikHaproxyCsv([
        'auth_primary' => ['UP', 'DOWN', 'DOWN'],
        'auth_replica' => ['DOWN', 'UP', 'DOWN'],
    ]));

    PollCluster::dispatch();

    $haproxy = AuthentikMonitorStatus::where('service', 'haproxy')->where('node_ip', '10.40.50.1')->first();
    expect($haproxy->status)->toBe('up');
});

test('polling twice upserts the same row per service+node_ip instead of accumulating duplicates', function () {
    config([
        'authentik.nodes' => [
            ['ip' => '10.40.50.1', 'name' => 'ak-1', 'base_priority' => 100],
        ],
        'authentik.vip' => null,
        'authentik.credentials.authentik_api_token' => null,
    ]);

    // A second Http::fake() call merges rather than replaces stubs (the
    // first-registered match for a URL always wins), so switching the
    // haproxy response between the two polls has to go through a sequence
    // on a single fake() call rather than re-faking in between.
    Http::fake([
        'http://10.40.50.1:9000/stats;csv' => Http::sequence()
            ->push(authentikHaproxyCsv(['pool' => ['UP']]), 200)
            ->push(authentikHaproxyCsv(['pool' => ['DOWN']]), 200),
        'http://10.40.50.1:8008/history' => Http::response([], 200),
        'http://10.40.50.1:8008/' => Http::response(['role' => 'primary', 'state' => 'running'], 200),
        'http://10.40.50.1:2379/health' => Http::response(['health' => true], 200),
        'http://10.40.50.1:2379/v3/maintenance/status' => Http::response([
            'header' => ['member_id' => 1], 'leader' => 1, 'raftTerm' => 3, 'dbSizeInUse' => 4096,
        ], 200),
        'https://10.40.50.1/monitor' => Http::response('', 200),
        'https://10.40.50.1:9443/-/health/live/' => Http::response('', 200),
        'http://10.40.50.1:8080/nginx_status' => Http::response("Active connections: 1 \nReading: 0 Writing: 1 Waiting: 0\n", 200),
        '*' => Http::response('', 200),
    ]);

    PollCluster::dispatch();
    $firstCount = AuthentikMonitorStatus::count();

    PollCluster::dispatch();

    expect(AuthentikMonitorStatus::count())->toBe($firstCount);
    $haproxy = AuthentikMonitorStatus::where('service', 'haproxy')->where('node_ip', '10.40.50.1')->first();
    expect($haproxy->status)->toBe('degraded');
});
