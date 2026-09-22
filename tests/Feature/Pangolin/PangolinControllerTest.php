<?php

use App\Jobs\Pangolin\PollCluster;
use App\Jobs\Pangolin\RunImport;
use App\Jobs\Pangolin\RunLogsFetch;
use App\Jobs\Pangolin\RunNormalize;
use App\Models\PangolinMonitorSettings;
use App\Models\PangolinNewtAgent;
use App\Models\PangolinRun;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function () {
    config([
        'pangolin.nodes' => [],
        'pangolin.vip' => null,
    ]);
    enableMenu('pangolin');
});

function fakePangolinImportXlsx(): UploadedFile
{
    $spreadsheet = new Spreadsheet();
    $spreadsheet->getActiveSheet()->setCellValue('A1', 'user@uop.gr');

    $path = tempnam(sys_get_temp_dir(), 'pangolin-import') . '.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, 'requests.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

test('monitor refresh dispatches PollCluster and redirects to the monitor tab', function () {
    Queue::fake();
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('pangolin.monitor.refresh'))
        ->assertRedirect(route('pangolin.index', ['tab' => 'monitor']))
        ->assertSessionHas('success');

    Queue::assertPushed(PollCluster::class, 1);
});

test('monitor data returns statuses grouped by service as json', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson(route('pangolin.monitor.data'))->assertOk();
});

test('monitor settings update saves the node ip and url, encrypts the key, and re-polls', function () {
    Queue::fake();
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('pangolin.monitor.settings.update'), [
        'node_ip' => '10.23.2.71',
        'pangolin_url' => 'https://pangolin.uop.gr/',
        'api_key' => 'super-secret-key',
    ])
        ->assertRedirect(route('pangolin.index', ['tab' => 'monitor']))
        ->assertSessionHas('success');

    $settings = PangolinMonitorSettings::first();
    expect($settings->node_ip)->toBe('10.23.2.71');
    // Trailing slash stripped for consistent concatenation in ClusterMonitor.
    expect($settings->pangolin_url)->toBe('https://pangolin.uop.gr');
    expect($settings->api_key)->not->toBe('super-secret-key');
    expect(Crypt::decryptString($settings->api_key))->toBe('super-secret-key');

    Queue::assertPushed(PollCluster::class, 1);
});

test('monitor settings update leaves the key untouched when the field is left blank', function () {
    Queue::fake(); // monitorSettingsUpdate() re-polls on save — without this, PollCluster::dispatch() runs for real under QUEUE_CONNECTION=sync and hits the real network.
    $user = User::factory()->create();
    PangolinMonitorSettings::create([
        'node_ip' => '10.23.2.71',
        'pangolin_url' => 'https://pangolin.uop.gr',
        'api_key' => Crypt::encryptString('original-key'),
    ]);

    $this->actingAs($user)->post(route('pangolin.monitor.settings.update'), [
        'node_ip' => '10.23.2.72',
        'pangolin_url' => 'https://pangolin.uop.gr',
    ])->assertRedirect(route('pangolin.index', ['tab' => 'monitor']));

    $settings = PangolinMonitorSettings::first();
    expect($settings->node_ip)->toBe('10.23.2.72');
    expect(Crypt::decryptString($settings->api_key))->toBe('original-key');
});

test('monitor settings update requires a valid ip', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('pangolin.monitor.settings.update'), ['node_ip' => 'not-an-ip'])
        ->assertSessionHasErrors('node_ip');
});

test('newt agents can be added, re-polling immediately, and duplicate ips are rejected', function () {
    Queue::fake();
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('pangolin.monitor.newt-agents.store'), [
        'name' => 'patra',
        'ip' => '10.23.2.60',
    ])->assertRedirect(route('pangolin.index', ['tab' => 'monitor']));

    expect(PangolinNewtAgent::where('ip', '10.23.2.60')->first()?->name)->toBe('patra');
    Queue::assertPushed(PollCluster::class, 1);

    $this->actingAs($user)->post(route('pangolin.monitor.newt-agents.store'), [
        'name' => 'patra-again',
        'ip' => '10.23.2.60',
    ])->assertSessionHasErrors('ip');
});

test('newt agents require a name and a valid ip', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('pangolin.monitor.newt-agents.store'), [])
        ->assertSessionHasErrors(['name', 'ip']);

    $this->actingAs($user)->post(route('pangolin.monitor.newt-agents.store'), [
        'name' => 'patra', 'ip' => 'not-an-ip',
    ])->assertSessionHasErrors('ip');
});

test('a newt agent can be removed', function () {
    $user = User::factory()->create();
    $agent = PangolinNewtAgent::create(['name' => 'patra', 'ip' => '10.23.2.60']);

    $this->actingAs($user)->delete(route('pangolin.monitor.newt-agents.destroy', $agent))
        ->assertRedirect(route('pangolin.index', ['tab' => 'monitor']));

    expect(PangolinNewtAgent::find($agent->id))->toBeNull();
});

test('logs fetch rejects an out-of-range lookback and otherwise queues a run', function () {
    Queue::fake();
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('pangolin.logs.fetch'), ['lookback_hours' => 999])
        ->assertSessionHasErrors('lookback_hours');

    $this->actingAs($user)->post(route('pangolin.logs.fetch'), ['lookback_hours' => 24, 'level' => 'error'])
        ->assertRedirect(route('pangolin.index', ['tab' => 'logs']))
        ->assertSessionHas('success');

    $run = PangolinRun::first();
    expect($run->type)->toBe('logs');
    expect($run->user_id)->toBe($user->id);
    expect($run->options['lookback_hours'])->toBe(24);
    expect($run->options['level'])->toBe('error');
    Queue::assertPushed(RunLogsFetch::class, 1);
});

test('logs fetch rejects an invalid level', function () {
    Queue::fake();
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('pangolin.logs.fetch'), ['level' => 'trace'])
        ->assertSessionHasErrors('level');
});

test('logs download 404s for a run of the wrong type or a missing file, and streams a real file otherwise', function () {
    $user = User::factory()->create();

    $importRun = PangolinRun::factory()->create(['type' => 'import', 'user_id' => $user->id]);
    $this->actingAs($user)->get(route('pangolin.logs.download', $importRun))->assertNotFound();

    $missingFileRun = PangolinRun::factory()->create(['type' => 'logs', 'user_id' => $user->id, 'report_path' => '/nonexistent/path.log']);
    $this->actingAs($user)->get(route('pangolin.logs.download', $missingFileRun))->assertNotFound();

    $logPath = storage_path('app/private/pangolin/runs/test-log.log');
    File::ensureDirectoryExists(dirname($logPath));
    File::put($logPath, 'log contents');
    $realRun = PangolinRun::factory()->create(['type' => 'logs', 'user_id' => $user->id, 'report_path' => $logPath]);

    $this->actingAs($user)->get(route('pangolin.logs.download', $realRun))->assertOk();

    File::delete($logPath);
});

test('resources import validates the upload and stores dry_run correctly from the checkbox-plus-hidden-field pair', function () {
    Queue::fake();
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('pangolin.resources.import'), [])
        ->assertSessionHasErrors('file');

    $this->actingAs($user)->post(route('pangolin.resources.import'), [
        'file' => UploadedFile::fake()->create('requests.txt', 10, 'text/plain'),
    ])->assertSessionHasErrors('file');

    // dry_run unchecked: only the hidden 0 is submitted.
    $this->actingAs($user)->post(route('pangolin.resources.import'), [
        'file' => fakePangolinImportXlsx(),
        'dry_run' => '0',
    ])->assertRedirect(route('pangolin.index', ['tab' => 'import']));

    $run = PangolinRun::first();
    expect($run->type)->toBe('import');
    expect($run->options['dry_run'])->toBeFalse();
    expect($run->options['original_filename'])->toBe('requests.xlsx');
    Queue::assertPushed(RunImport::class, 1);
});

test('resources normalize requires the confirm checkbox when apply is checked, and parses the resource_ids list', function () {
    Queue::fake();
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('pangolin.resources.normalize'), ['apply' => '1'])
        ->assertSessionHasErrors('confirm');

    $this->actingAs($user)->post(route('pangolin.resources.normalize'), [
        'apply' => '1',
        'confirm' => '1',
        'resource_ids' => ' nice-id-1 , 42 ,, nice-id-2 ',
    ])->assertRedirect(route('pangolin.index', ['tab' => 'normalize']));

    $run = PangolinRun::first();
    expect($run->options['apply'])->toBeTrue();
    expect($run->options['resource_ids'])->toBe(['nice-id-1', '42', 'nice-id-2']);
    Queue::assertPushed(RunNormalize::class, 1);
});

test('resources normalize without apply does not require confirmation', function () {
    Queue::fake();
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('pangolin.resources.normalize'), [])
        ->assertRedirect(route('pangolin.index', ['tab' => 'normalize']));

    expect(PangolinRun::first()->options['apply'])->toBeFalse();
});

test('resources download 404s for a run of the wrong type or a missing file', function () {
    $user = User::factory()->create();

    $logsRun = PangolinRun::factory()->create(['type' => 'logs', 'user_id' => $user->id, 'report_path' => '/whatever']);
    $this->actingAs($user)->get(route('pangolin.resources.download', $logsRun))->assertNotFound();

    $normalizeRun = PangolinRun::factory()->create(['type' => 'normalize', 'user_id' => $user->id, 'report_path' => null]);
    $this->actingAs($user)->get(route('pangolin.resources.download', $normalizeRun))->assertNotFound();
});
