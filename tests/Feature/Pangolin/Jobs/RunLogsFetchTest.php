<?php

use App\Jobs\Pangolin\RunLogsFetch;
use App\Models\PangolinRun;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use romanzipp\QueueMonitor\Models\Monitor;

beforeEach(function () {
    config([
        'pangolin.nodes' => [],
        'pangolin.newt.hosts' => [],
        'pangolin.vip' => null,
    ]);
    // Run directories are named after the PangolinRun id, and sqlite's
    // :memory: RefreshDatabase rolls back per test rather than recreating the
    // schema — ids restart from 1 every test, so leftover files from a
    // previous test's run would otherwise be inherited by this one.
    File::deleteDirectory(storage_path('app/private/pangolin'));
});

test('a successful fetch marks the run completed and records both the log and newt csv paths', function () {
    Process::fake(function ($process) {
        file_put_contents($process->path . '/cluster_logs.log', 'WARN something happened');
        file_put_contents($process->path . '/cluster_logs_newt.csv', 'a,b,c');

        return Process::result(output: 'done', exitCode: 0);
    });

    $run = PangolinRun::factory()->create(['type' => 'logs', 'status' => 'queued']);

    RunLogsFetch::dispatch($run);

    $run->refresh();
    expect($run->status)->toBe('completed');
    expect($run->report_path)->not->toBeNull();
    expect($run->extra_path)->not->toBeNull();
    expect($run->started_at)->not->toBeNull();
    expect($run->finished_at)->not->toBeNull();

    $monitor = Monitor::where('name', RunLogsFetch::class)->first();
    expect($monitor)->not->toBeNull();
});

test('a fetch with no newt hosts configured leaves extra_path null even on success', function () {
    Process::fake(function ($process) {
        file_put_contents($process->path . '/cluster_logs.log', 'WARN something happened');

        return Process::result(output: 'done', exitCode: 0);
    });

    $run = PangolinRun::factory()->create(['type' => 'logs']);

    RunLogsFetch::dispatch($run);

    expect($run->fresh()->extra_path)->toBeNull();
});

test('a failed script run marks the run failed with the exit code recorded', function () {
    Process::fake(fn () => Process::result(output: '', errorOutput: 'boom', exitCode: 1));

    $run = PangolinRun::factory()->create(['type' => 'logs']);

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
        file_put_contents($process->path . '/cluster_logs.log', 'ok');

        return Process::result(exitCode: 0);
    });

    $run = PangolinRun::factory()->create(['type' => 'logs', 'options' => ['lookback_hours' => 48]]);

    RunLogsFetch::dispatch($run);

    expect($capturedCommand)->toContain('--last', '48');
});

test('the level option is passed through as a --level argument', function () {
    $capturedCommand = null;
    Process::fake(function ($process) use (&$capturedCommand) {
        $capturedCommand = $process->command;
        file_put_contents($process->path . '/cluster_logs.log', 'ok');

        return Process::result(exitCode: 0);
    });

    $run = PangolinRun::factory()->create(['type' => 'logs', 'options' => ['level' => 'error']]);

    RunLogsFetch::dispatch($run);

    expect($capturedCommand)->toContain('--level', 'error');
});
