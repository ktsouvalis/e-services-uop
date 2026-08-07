<?php

use App\Jobs\Authentik\RunLogsFetch;
use App\Models\AuthentikLogRun;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use romanzipp\QueueMonitor\Models\Monitor;

beforeEach(function () {
    config([
        'authentik.nodes' => [],
        'authentik.vip' => null,
        'authentik.credentials.authentik_api_token' => null,
    ]);
    // Run directories are named after the AuthentikLogRun id, and sqlite's
    // :memory: RefreshDatabase rolls back per test rather than recreating the
    // schema — ids restart from 1 every test, so leftover files from a
    // previous test's run would otherwise be inherited by this one.
    File::deleteDirectory(storage_path('app/private/authentik'));
});

test('a successful fetch marks the run completed and records the log path', function () {
    Process::fake(function ($process) {
        file_put_contents($process->path.'/cluster_logs.log', 'WARN something happened');

        return Process::result(output: 'done', exitCode: 0);
    });

    $run = AuthentikLogRun::factory()->create(['status' => 'queued']);

    RunLogsFetch::dispatch($run);

    $run->refresh();
    expect($run->status)->toBe('completed');
    expect($run->report_path)->not->toBeNull();
    expect($run->started_at)->not->toBeNull();
    expect($run->finished_at)->not->toBeNull();

    $monitor = Monitor::where('name', RunLogsFetch::class)->first();
    expect($monitor)->not->toBeNull();
});

test('a failed script run marks the run failed with the exit code recorded', function () {
    Process::fake(fn () => Process::result(output: '', errorOutput: 'boom', exitCode: 1));

    $run = AuthentikLogRun::factory()->create();

    RunLogsFetch::dispatch($run);

    $run->refresh();
    expect($run->status)->toBe('failed');
    expect($run->report_path)->toBeNull();
    expect($run->error)->toBe('Exit code 1');
});

test('the lookback_hours option is passed through as a --last argument', function () {
    $capturedCommand = null;
    Process::fake(function ($process) use (&$capturedCommand) {
        $capturedCommand = $process->command;
        file_put_contents($process->path.'/cluster_logs.log', 'ok');

        return Process::result(exitCode: 0);
    });

    $run = AuthentikLogRun::factory()->create(['options' => ['lookback_hours' => 6]]);

    RunLogsFetch::dispatch($run);

    expect($capturedCommand)->toContain('--last', '6');
});
