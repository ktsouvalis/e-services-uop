<?php

use App\Jobs\Pangolin\PollCluster;
use App\Jobs\Pangolin\RunImport;
use App\Jobs\Pangolin\RunLogsFetch;
use App\Jobs\Pangolin\RunNormalize;
use App\Models\PangolinRun;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function () {
    config([
        'pangolin.nodes' => [],
        'pangolin.newt.hosts' => [],
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
