<?php

use App\Jobs\Authentik\PollCluster;
use App\Jobs\Authentik\RunLogsFetch;
use App\Models\AuthentikLogRun;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config([
        'authentik.nodes' => [],
        'authentik.vip' => null,
        'authentik.credentials.authentik_api_token' => null,
    ]);
    enableMenu('authentik');
});

test('monitor refresh dispatches PollCluster and redirects to the monitor tab', function () {
    Queue::fake();
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('authentik.monitor.refresh'))
        ->assertRedirect(route('authentik.index', ['tab' => 'monitor']))
        ->assertSessionHas('success');

    Queue::assertPushed(PollCluster::class, 1);
});

test('monitor data returns statuses grouped by service as json', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson(route('authentik.monitor.data'))->assertOk();
});

test('logs fetch rejects an out-of-range lookback and otherwise queues a run', function () {
    Queue::fake();
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('authentik.logs.fetch'), ['lookback_hours' => 999])
        ->assertSessionHasErrors('lookback_hours');

    $this->actingAs($user)->post(route('authentik.logs.fetch'), ['lookback_hours' => 12])
        ->assertRedirect(route('authentik.index', ['tab' => 'logs']))
        ->assertSessionHas('success');

    $run = AuthentikLogRun::first();
    expect($run->user_id)->toBe($user->id);
    expect($run->options['lookback_hours'])->toBe(12);
    Queue::assertPushed(RunLogsFetch::class, 1);
});

test('logs download 404s on a missing file and streams a real file otherwise', function () {
    $user = User::factory()->create();

    $missingFileRun = AuthentikLogRun::factory()->create(['report_path' => '/nonexistent/path.log']);
    $this->actingAs($user)->get(route('authentik.logs.download', $missingFileRun))->assertNotFound();

    $logPath = storage_path('app/private/authentik/runs/test-log.log');
    File::ensureDirectoryExists(dirname($logPath));
    File::put($logPath, 'log contents');
    $realRun = AuthentikLogRun::factory()->create(['report_path' => $logPath]);

    $this->actingAs($user)->get(route('authentik.logs.download', $realRun))->assertOk();

    File::delete($logPath);
});
