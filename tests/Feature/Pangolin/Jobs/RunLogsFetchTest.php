<?php

use App\Jobs\Pangolin\RunLogsFetch;
use App\Models\PangolinNewtAgent;
use App\Models\PangolinRun;
use App\Services\Pangolin\NewtAccessLogResolver;
use App\Services\Pangolin\SshCommandRunner;
use Illuminate\Support\Facades\File;
use romanzipp\QueueMonitor\Models\Monitor;

beforeEach(function () {
    config([
        'pangolin.nodes' => [['ip' => '10.20.30.1', 'name' => 'node-1']],
        'pangolin.vip' => null,
        'pangolin.ssh' => ['username' => 'cluster-user', 'key_path' => '/keys/cluster'],
        'pangolin.newt' => ['ssh' => ['username' => null, 'key_path' => null]],
        'pangolin.services' => [
            ['label' => 'Pangolin', 'nodes' => 'pangolin', 'type' => 'docker', 'container' => 'pangolin'],
            ['label' => 'Newt', 'nodes' => 'newt', 'type' => 'docker', 'container' => 'newt'],
        ],
    ]);
    // Run directories are named after the PangolinRun id, and sqlite's
    // :memory: RefreshDatabase rolls back per test rather than recreating the
    // schema — ids restart from 1 every test, so leftover files from a
    // previous test's run would otherwise be inherited by this one.
    File::deleteDirectory(storage_path('app/private/pangolin'));
});

/** Every SSH command (service logs, or the full newt log) returns this. */
function fakeSshAlwaysReturning(string $output): void
{
    $mock = Mockery::mock(SshCommandRunner::class);
    $mock->shouldReceive('run')->andReturn($output);
    app()->instance(SshCommandRunner::class, $mock);
}

/** Postgres unreachable in the test environment — the expected default: falls back to raw ids. */
function fakePostgresUnreachable(): void
{
    app()->instance(NewtAccessLogResolver::class, new class extends NewtAccessLogResolver
    {
        public function connect(): \PDO
        {
            throw new \RuntimeException('could not connect to server');
        }
    });
}

test('a successful fetch marks the run completed and records both the log and newt csv paths', function () {
    fakeSshAlwaysReturning('WARN something happened');
    fakePostgresUnreachable();
    PangolinNewtAgent::create(['name' => 'patra', 'ip' => '10.23.2.60']);

    $run = PangolinRun::factory()->create(['type' => 'logs', 'status' => 'queued']);

    RunLogsFetch::dispatch($run);

    $run->refresh();
    expect($run->status)->toBe('completed');
    expect($run->report_path)->not->toBeNull();
    expect($run->extra_path)->not->toBeNull();
    expect($run->started_at)->not->toBeNull();
    expect($run->finished_at)->not->toBeNull();
    expect(file_get_contents($run->report_path))->toContain('WARN something happened');

    $monitor = Monitor::where('name', RunLogsFetch::class)->first();
    expect($monitor)->not->toBeNull();
});

test('a fetch with no newt agents configured leaves extra_path null even on success', function () {
    fakeSshAlwaysReturning('ok');
    fakePostgresUnreachable();

    $run = PangolinRun::factory()->create(['type' => 'logs']);

    RunLogsFetch::dispatch($run);

    $run->refresh();
    expect($run->status)->toBe('completed');
    expect($run->extra_path)->toBeNull();
});

test('an SSH failure for one service is recorded in the report rather than failing the whole run', function () {
    $mock = Mockery::mock(SshCommandRunner::class);
    $mock->shouldReceive('run')->andThrow(new RuntimeException('SSH authentication failed'));
    app()->instance(SshCommandRunner::class, $mock);
    fakePostgresUnreachable();

    $run = PangolinRun::factory()->create(['type' => 'logs']);

    RunLogsFetch::dispatch($run);

    $run->refresh();
    expect($run->status)->toBe('completed');
    expect(file_get_contents($run->report_path))->toContain('SSH error: SSH authentication failed');
});

test('an unreachable Postgres falls back to raw ids in the newt csv rather than failing the run', function () {
    fakeSshAlwaysReturning(
        'ACCESS START session=s1 resource=35 proto=tcp src=10.1.2.3:5000 dst=10.23.2.50:22 time=2026-09-22T10:00:00Z'."\n".
        'ACCESS END session=s1 resource=35 proto=tcp src=10.1.2.3:5000 dst=10.23.2.50:22 started=2026-09-22T10:00:00Z ended=2026-09-22T10:05:00Z duration=5m'
    );
    fakePostgresUnreachable();
    PangolinNewtAgent::create(['name' => 'patra', 'ip' => '10.23.2.60']);

    $run = PangolinRun::factory()->create(['type' => 'logs']);

    RunLogsFetch::dispatch($run);

    $run->refresh();
    expect($run->status)->toBe('completed');
    $csv = file_get_contents($run->extra_path);
    // Raw src ip (10.1.2.3), not a resolved client name — the DB lookup
    // failed, so formatSession() fell back to its raw-value defaults.
    expect($csv)->toContain('10.1.2.3');
});

test('the lookback_hours option overrides both the service-log and newt access-log windows', function () {
    $mock = Mockery::mock(SshCommandRunner::class);
    $mock->shouldReceive('run')->with(Mockery::any(), Mockery::any(), Mockery::any(), Mockery::pattern('/--since 48h pangolin/'))->andReturn('ok');
    $mock->shouldReceive('run')->with(Mockery::any(), Mockery::any(), Mockery::any(), 'docker logs --since 48h newt 2>&1')->andReturn('');
    app()->instance(SshCommandRunner::class, $mock);
    fakePostgresUnreachable();
    PangolinNewtAgent::create(['name' => 'patra', 'ip' => '10.23.2.60']);

    $run = PangolinRun::factory()->create(['type' => 'logs', 'options' => ['lookback_hours' => 48]]);

    RunLogsFetch::dispatch($run);

    expect($run->fresh()->status)->toBe('completed');
});

test('the level option changes the grep pattern used for service logs', function () {
    $mock = Mockery::mock(SshCommandRunner::class);
    $mock->shouldReceive('run')->with(Mockery::any(), Mockery::any(), Mockery::any(), Mockery::pattern("/grep -iE '\(ERROR\|CRITICAL\|FATAL\|CRIT\)'/"))->andReturn('ok');
    app()->instance(SshCommandRunner::class, $mock);
    fakePostgresUnreachable();

    $run = PangolinRun::factory()->create(['type' => 'logs', 'options' => ['level' => 'error']]);

    RunLogsFetch::dispatch($run);

    expect($run->fresh()->status)->toBe('completed');
});

test('no configured services/nodes fails the run cleanly', function () {
    config(['pangolin.services' => [], 'pangolin.nodes' => []]);

    $run = PangolinRun::factory()->create(['type' => 'logs']);

    RunLogsFetch::dispatch($run);

    $run->refresh();
    expect($run->status)->toBe('failed');
    expect($run->error)->not->toBeNull();
});
