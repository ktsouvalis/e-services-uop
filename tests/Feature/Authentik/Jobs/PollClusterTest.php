<?php

use App\Jobs\Authentik\PollCluster;
use App\Models\AuthentikMonitorSettings;
use App\Models\AuthentikMonitorStatus;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

function fakeAuthentikSettings(bool $withToken = true): AuthentikMonitorSettings
{
    return AuthentikMonitorSettings::create([
        'node_ip' => '10.23.2.71',
        'authentik_url' => 'https://auth.uop.gr',
        'api_token' => $withToken ? Crypt::encryptString('test-token') : null,
    ]);
}

test('with no settings saved, only the authentik row is written, flagged unknown', function () {
    PollCluster::dispatch();

    expect(AuthentikMonitorStatus::count())->toBe(1);
    $row = AuthentikMonitorStatus::first();
    expect($row->service)->toBe('authentik');
    expect($row->status)->toBe('unknown');
});

test('a fully healthy poll marks every service up', function () {
    fakeAuthentikSettings();
    Http::fake([
        'https://10.23.2.71/-/health/live/' => Http::response('', 200),
        'http://10.23.2.71:8080/nginx_status' => Http::response(
            "Active connections: 5\nserver accepts handled requests\n 1 1 1\nReading: 0 Writing: 1 Waiting: 4",
            200
        ),
        'https://auth.uop.gr/api/v3/tasks/workers/' => Http::response([
            ['worker_id' => 'abc@authentik-patra', 'version_matching' => true],
        ], 200),
        'https://auth.uop.gr/api/v3/tasks/tasks/status/' => Http::response([
            'queued' => 0, 'running' => 1, 'rejected' => 0, 'error' => 0, 'warning' => 0, 'done' => 10,
        ], 200),
    ]);

    PollCluster::dispatch();

    // 4 rows: authentik, nginx, workers, worker_queue — no direct worker
    // liveness port check (dropped, see ClusterMonitor's docblock).
    expect(AuthentikMonitorStatus::count())->toBe(4);
    expect(AuthentikMonitorStatus::where('status', 'up')->count())->toBe(4);

    $nginx = AuthentikMonitorStatus::where('service', 'nginx')->first();
    expect($nginx->metrics['active'])->toBe(5);
    expect($nginx->metrics['waiting'])->toBe(4);

    $workers = AuthentikMonitorStatus::where('service', 'workers')->first();
    expect($workers->metrics['count'])->toBe(1);
});

test('an unreachable node marks the direct checks down without touching the API checks', function () {
    fakeAuthentikSettings();
    Http::fake([
        'https://10.23.2.71/-/health/live/' => Http::response('', 503),
        'http://10.23.2.71:8080/nginx_status' => Http::response('', 500),
        'https://auth.uop.gr/api/v3/tasks/workers/' => Http::response([], 200),
        'https://auth.uop.gr/api/v3/tasks/tasks/status/' => Http::response(['queued' => 0, 'running' => 0], 200),
    ]);

    PollCluster::dispatch();

    expect(AuthentikMonitorStatus::where('service', 'authentik')->first()->status)->toBe('down');
    expect(AuthentikMonitorStatus::where('service', 'nginx')->first()->status)->toBe('down');
});

test('without an api token, the workers and worker_queue rows are unknown rather than down', function () {
    fakeAuthentikSettings(withToken: false);
    Http::fake([
        'https://10.23.2.71/-/health/live/' => Http::response('', 200),
        'http://10.23.2.71:8080/nginx_status' => Http::response('Active connections: 1', 200),
    ]);

    PollCluster::dispatch();

    expect(AuthentikMonitorStatus::where('service', 'workers')->first()->status)->toBe('unknown');
    expect(AuthentikMonitorStatus::where('service', 'worker_queue')->first()->status)->toBe('unknown');
});

test('a rejected or errored task queue degrades or fails the worker_queue row', function () {
    fakeAuthentikSettings();
    Http::fake([
        'https://10.23.2.71/-/health/live/' => Http::response('', 200),
        'http://10.23.2.71:8080/nginx_status' => Http::response('Active connections: 1', 200),
        'https://auth.uop.gr/api/v3/tasks/workers/' => Http::response([], 200),
        'https://auth.uop.gr/api/v3/tasks/tasks/status/' => Http::response([
            'queued' => 0, 'running' => 1, 'rejected' => 2, 'error' => 0, 'warning' => 0, 'done' => 10,
        ], 200),
    ]);

    PollCluster::dispatch();

    expect(AuthentikMonitorStatus::where('service', 'worker_queue')->first()->status)->toBe('degraded');
});

test('polling twice upserts the same row per service instead of accumulating duplicates', function () {
    fakeAuthentikSettings(withToken: false);
    Http::fake([
        'https://10.23.2.71/-/health/live/' => Http::sequence()
            ->push('', 200)
            ->push('', 503),
        'http://10.23.2.71:8080/nginx_status' => Http::response('Active connections: 1', 200),
    ]);

    PollCluster::dispatch();
    $firstCount = AuthentikMonitorStatus::count();

    PollCluster::dispatch();

    expect(AuthentikMonitorStatus::count())->toBe($firstCount);
    expect(AuthentikMonitorStatus::where('service', 'authentik')->first()->status)->toBe('down');
});
