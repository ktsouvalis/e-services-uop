<?php

use App\Jobs\Authentik\PollCluster;
use App\Jobs\Authentik\RunLogsFetch;
use App\Models\AuthentikLogRun;
use App\Models\AuthentikMonitorSettings;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;

function validAuthentikYaml(): string
{
    return "site_name: example-site\nvip: 10.0.0.1\n";
}

beforeEach(function () {
    enableMenu('authentik');
    File::deleteDirectory(storage_path('app/private/authentik'));
});

// afterEach, not just beforeEach: storage_path() isn't swapped out for tests
// the way DB/cache/queue are (see CLAUDE.md's make test note) — it's the
// real, shared dev storage tree, and `make test` runs as root. Without this,
// whichever test in the suite happens to run last leaves root-owned files
// behind at real paths this module also uses for actual requests (worst hit:
// RememberedConfig's single fixed-path last-config.yml) — root-owned files
// a real www-data web request then can't read, breaking the live app with a
// permission error that has nothing to do with any bug in that request.
// Confirmed live: this is exactly what happened before this was added.
afterEach(function () {
    File::deleteDirectory(storage_path('app/private/authentik'));
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

test('monitor settings update saves the node ip and url, encrypts the token, and re-polls', function () {
    Queue::fake();
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('authentik.monitor.settings.update'), [
        'node_ip' => '10.23.2.71',
        'authentik_url' => 'https://auth.uop.gr/',
        'api_token' => 'super-secret-token',
    ])
        ->assertRedirect(route('authentik.index', ['tab' => 'monitor']))
        ->assertSessionHas('success');

    $settings = AuthentikMonitorSettings::first();
    expect($settings->node_ip)->toBe('10.23.2.71');
    // Trailing slash stripped for consistent concatenation in ClusterMonitor.
    expect($settings->authentik_url)->toBe('https://auth.uop.gr');
    expect($settings->api_token)->not->toBe('super-secret-token');
    expect(Crypt::decryptString($settings->api_token))->toBe('super-secret-token');

    Queue::assertPushed(PollCluster::class, 1);
});

test('monitor settings update leaves the token untouched when the field is left blank', function () {
    Queue::fake(); // monitorSettingsUpdate() re-polls on save — without this, PollCluster::dispatch() runs for real under QUEUE_CONNECTION=sync and hits the real network.
    $user = User::factory()->create();
    AuthentikMonitorSettings::create([
        'node_ip' => '10.23.2.71',
        'authentik_url' => 'https://auth.uop.gr',
        'api_token' => Crypt::encryptString('original-token'),
    ]);

    $this->actingAs($user)->post(route('authentik.monitor.settings.update'), [
        'node_ip' => '10.23.2.72',
        'authentik_url' => 'https://auth.uop.gr',
    ])->assertRedirect(route('authentik.index', ['tab' => 'monitor']));

    $settings = AuthentikMonitorSettings::first();
    expect($settings->node_ip)->toBe('10.23.2.72');
    expect(Crypt::decryptString($settings->api_token))->toBe('original-token');
});

test('monitor settings update requires a valid ip', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('authentik.monitor.settings.update'), ['node_ip' => 'not-an-ip'])
        ->assertSessionHasErrors('node_ip');
});

test('logs fetch requires a pasted config and rejects an out-of-range lookback', function () {
    $validYaml = validAuthentikYaml();
    Queue::fake();
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('authentik.logs.fetch'), ['lookback_hours' => 999])
        ->assertSessionHasErrors(['config_yml', 'lookback_hours']);

    $this->actingAs($user)->post(route('authentik.logs.fetch'), [
        'config_yml' => $validYaml,
        'lookback_hours' => 12,
        'level' => 'info',
    ])
        ->assertRedirect(route('authentik.index', ['tab' => 'logs']))
        ->assertSessionHas('success');

    $run = AuthentikLogRun::first();
    expect($run->user_id)->toBe($user->id);
    expect($run->options['lookback_hours'])->toBe(12);
    expect($run->options['level'])->toBe('info');
    expect(file_get_contents(storage_path("app/private/authentik/runs/{$run->id}/config.yml")))->toBe(trim($validYaml));
    Queue::assertPushed(RunLogsFetch::class, 1);
});

test('logs fetch rejects an invalid level', function () {
    Queue::fake();
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('authentik.logs.fetch'), ['config_yml' => validAuthentikYaml(), 'level' => 'trace'])
        ->assertSessionHasErrors('level');
});

test('logs fetch rejects text that is not valid YAML', function () {
    Queue::fake();
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('authentik.logs.fetch'), ['config_yml' => "site_name: [unclosed\n"])
        ->assertSessionHasErrors('config_yml');
});

test('a fetched config is remembered and pre-fills the textarea on the next visit', function () {
    Queue::fake();
    $user = User::factory()->create();
    $yaml = validAuthentikYaml();

    $this->actingAs($user)->get(route('authentik.index'))->assertDontSee('example-site');

    $this->actingAs($user)->post(route('authentik.logs.fetch'), ['config_yml' => $yaml]);

    $this->actingAs($user)->get(route('authentik.index'))->assertSee(trim($yaml), false);
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
