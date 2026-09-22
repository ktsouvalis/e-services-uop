<?php

use App\Jobs\Pangolin\PollCluster;
use App\Models\PangolinMonitorSettings;
use App\Models\PangolinMonitorStatus;
use App\Models\PangolinNewtAgent;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

// No beforeEach needed to isolate Newt hosts from real .env leakage (see
// CLAUDE.md's Testing section) — pangolin_newt_agents is a plain DB table
// now, empty by default under RefreshDatabase, not an env-config key.

test('with no settings configured, a single unknown pangolin row is recorded and no HTTP calls are made', function () {
    Http::fake();

    PollCluster::dispatch();

    Http::assertNothingSent();
    expect(PangolinMonitorStatus::count())->toBe(1);
    $row = PangolinMonitorStatus::first();
    expect($row->service)->toBe('pangolin');
    expect($row->status)->toBe('unknown');
});

test('a configured node with a reachable API records pangolin and gerbil as up, both inferred from the same endpoint', function () {
    PangolinMonitorSettings::create(['node_ip' => '10.20.30.1']);
    Http::fake([
        'http://10.20.30.1:3001/api/v1/' => Http::response('', 200),
    ]);

    PollCluster::dispatch();

    expect(PangolinMonitorStatus::where('service', 'pangolin')->first()->status)->toBe('up');
    expect(PangolinMonitorStatus::where('service', 'gerbil')->first()->status)->toBe('up');
});

test('an unreachable node records pangolin and gerbil as down', function () {
    PangolinMonitorSettings::create(['node_ip' => '10.20.30.1']);
    Http::fake([
        'http://10.20.30.1:3001/api/v1/' => Http::response('', 500),
    ]);

    PollCluster::dispatch();

    expect(PangolinMonitorStatus::where('service', 'pangolin')->first()->status)->toBe('down');
    expect(PangolinMonitorStatus::where('service', 'gerbil')->first()->status)->toBe('down');
});

test('without a url/api key configured, the api row is unknown and no Integration API call is made', function () {
    PangolinMonitorSettings::create(['node_ip' => '10.20.30.1']);
    Http::fake([
        'http://10.20.30.1:3001/api/v1/' => Http::response('', 200),
        '*' => Http::response('should not be called', 500),
    ]);

    PollCluster::dispatch();

    expect(PangolinMonitorStatus::where('service', 'api')->first()->status)->toBe('unknown');
});

test('a reachable, authorized Integration API records the api row as up with the resource total', function () {
    config(['pangolin.org_slug' => 'uop']);
    PangolinMonitorSettings::create([
        'node_ip' => '10.20.30.1',
        'pangolin_url' => 'https://pangolin.uop.gr',
        'api_key' => Crypt::encryptString('a-real-key'),
    ]);
    Http::fake([
        'http://10.20.30.1:3001/api/v1/' => Http::response('', 200),
        'https://pangolin.uop.gr/v1/org/uop/site-resources*' => Http::response([
            'data' => ['siteResources' => [], 'pagination' => ['total' => 7]],
        ], 200),
    ]);

    PollCluster::dispatch();

    $api = PangolinMonitorStatus::where('service', 'api')->first();
    expect($api->status)->toBe('up');
    expect($api->metrics['total_resources'])->toBe(7);
});

test('an unauthorized Integration API records the api row as down with a helpful message', function () {
    config(['pangolin.org_slug' => 'uop']);
    PangolinMonitorSettings::create([
        'node_ip' => '10.20.30.1',
        'pangolin_url' => 'https://pangolin.uop.gr',
        'api_key' => Crypt::encryptString('a-bad-key'),
    ]);
    Http::fake([
        'http://10.20.30.1:3001/api/v1/' => Http::response('', 200),
        'https://pangolin.uop.gr/v1/org/uop/site-resources*' => Http::response('', 401),
    ]);

    PollCluster::dispatch();

    $api = PangolinMonitorStatus::where('service', 'api')->first();
    expect($api->status)->toBe('down');
    expect($api->message)->toContain('unauthorized');
});

test('polling twice upserts the same row per service instead of accumulating duplicates', function () {
    PangolinMonitorSettings::create(['node_ip' => '10.20.30.1']);
    Http::fake([
        'http://10.20.30.1:3001/api/v1/' => Http::sequence()
            ->push('', 200)
            ->push('', 500),
    ]);

    PollCluster::dispatch();
    $firstCount = PangolinMonitorStatus::count();

    PollCluster::dispatch();

    expect(PangolinMonitorStatus::count())->toBe($firstCount);
    expect(PangolinMonitorStatus::where('service', 'pangolin')->first()->status)->toBe('down');
});

test('changing the configured node ip updates the existing row rather than forking a new one', function () {
    $settings = PangolinMonitorSettings::create(['node_ip' => '10.20.30.1']);
    Http::fake(['*' => Http::response('', 200)]);

    PollCluster::dispatch();
    expect(PangolinMonitorStatus::count())->toBe(3); // pangolin, gerbil, api(unknown)

    $settings->update(['node_ip' => '10.20.30.2']);
    PollCluster::dispatch();

    expect(PangolinMonitorStatus::count())->toBe(3);
    expect(PangolinMonitorStatus::where('service', 'pangolin')->first()->node_ip)->toBe('10.20.30.2');
});

test('every newt agent in the db gets its own row, keyed by name, independent of pangolin node settings', function () {
    // 127.0.0.0/8 is entirely loopback — distinct addresses to satisfy the
    // unique ip constraint, all routing to this same container with port 22
    // closed, same as plain 127.0.0.1.
    PangolinNewtAgent::create(['name' => 'newt-1', 'ip' => '127.0.0.1']);
    PangolinNewtAgent::create(['name' => 'newt-2', 'ip' => '127.0.0.2']);
    PangolinNewtAgent::create(['name' => 'newt-3', 'ip' => '127.0.0.3']);
    Http::fake();

    // No PangolinMonitorSettings row at all — Newt checks run regardless of
    // whether the single Pangolin node is configured. Same closed-port
    // 127.0.0.1:22 trick for all 3 rows — a real IP would work identically,
    // there's nothing keying behavior to a specific count of agents.
    PollCluster::dispatch();

    $newtRows = PangolinMonitorStatus::where('service', 'newt')->get();
    expect($newtRows)->toHaveCount(3);
    expect($newtRows->pluck('node_name')->sort()->values()->all())->toBe(['newt-1', 'newt-2', 'newt-3']);
    // No real SSH server on 127.0.0.1:22 in the test container — every row down.
    expect($newtRows->pluck('status')->unique()->all())->toBe(['down']);
    expect($newtRows->first()->message)->not->toBeNull();
});

test('polling twice upserts the same row per newt agent instead of accumulating duplicates', function () {
    PangolinNewtAgent::create(['name' => 'newt-1', 'ip' => '127.0.0.1']);
    Http::fake();

    PollCluster::dispatch();
    $firstCount = PangolinMonitorStatus::count();

    PollCluster::dispatch();

    expect(PangolinMonitorStatus::count())->toBe($firstCount);
    expect(PangolinMonitorStatus::where('service', 'newt')->count())->toBe(1);
});

test('removing a newt agent stops producing a row for it on the next poll', function () {
    $agent = PangolinNewtAgent::create(['name' => 'newt-1', 'ip' => '127.0.0.1']);
    Http::fake();

    PollCluster::dispatch();
    expect(PangolinMonitorStatus::where('service', 'newt')->count())->toBe(1);

    $agent->delete();
    PollCluster::dispatch();

    expect(PangolinMonitorStatus::where('service', 'newt')->count())->toBe(0);
});
