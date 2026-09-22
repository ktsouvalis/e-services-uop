<?php

use App\Jobs\Pangolin\RunImport;
use App\Jobs\Pangolin\RunNormalize;
use App\Models\PangolinRun;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function () {
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

    $normalizeRun = PangolinRun::factory()->create(['type' => 'normalize', 'user_id' => $user->id, 'report_path' => null]);
    $this->actingAs($user)->get(route('pangolin.resources.download', $normalizeRun))->assertNotFound();
});
