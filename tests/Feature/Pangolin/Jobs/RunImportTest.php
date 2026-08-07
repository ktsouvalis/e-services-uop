<?php

use App\Jobs\Pangolin\RunImport;
use App\Models\PangolinRun;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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

function makePangolinUploadedInputFile(): string
{
    $path = storage_path('app/private/pangolin/imports/'.uniqid('input', true).'.xlsx');
    File::ensureDirectoryExists(dirname($path));

    $spreadsheet = new Spreadsheet();
    $spreadsheet->getActiveSheet()->setCellValue('A1', 'user@uop.gr');
    (new Xlsx($spreadsheet))->save($path);

    return $path;
}

test('a successful import deletes the orphaned upload and records the report and its status summary', function () {
    $inputPath = makePangolinUploadedInputFile();

    Process::fake(function ($process) {
        $reportPath = $process->path.'/input_results_20260807.xlsx';

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Results');
        $sheet->setCellValue('A1', 'Email');
        $sheet->setCellValue('B1', 'Status');
        $sheet->setCellValue('A2', 'one@uop.gr');
        $sheet->setCellValue('B2', 'created');
        $sheet->setCellValue('A3', 'two@uop.gr');
        $sheet->setCellValue('B3', 'created');
        $sheet->setCellValue('A4', 'three@uop.gr');
        $sheet->setCellValue('B4', 'error');
        (new Xlsx($spreadsheet))->save($reportPath);

        return Process::result(output: 'done', exitCode: 0);
    });

    $run = PangolinRun::factory()->create(['type' => 'import', 'input_path' => $inputPath]);

    RunImport::dispatch($run);

    $run->refresh();
    expect($run->status)->toBe('completed');
    expect($run->report_path)->toContain('input_results_');
    expect($run->summary)->toBe(['created' => 2, 'error' => 1]);

    // The uploaded original under pangolin/imports/ must not be left behind
    // now that a copy lives in the run's own directory.
    expect(File::exists($inputPath))->toBeFalse();
});

test('the orphaned upload is still cleaned up even when the script itself fails', function () {
    $inputPath = makePangolinUploadedInputFile();
    Process::fake(fn () => Process::result(errorOutput: 'boom', exitCode: 1));

    $run = PangolinRun::factory()->create(['type' => 'import', 'input_path' => $inputPath]);

    RunImport::dispatch($run);

    expect($run->fresh()->status)->toBe('failed');
    expect(File::exists($inputPath))->toBeFalse();
});

test('dry_run is passed through as a --dry-run flag only when requested', function () {
    $inputPath = makePangolinUploadedInputFile();
    $capturedCommand = null;
    Process::fake(function ($process) use (&$capturedCommand) {
        $capturedCommand = $process->command;

        return Process::result(exitCode: 0);
    });

    $run = PangolinRun::factory()->create([
        'type' => 'import',
        'input_path' => $inputPath,
        'options' => ['dry_run' => true],
    ]);

    RunImport::dispatch($run);

    expect($capturedCommand)->toContain('--dry-run');
});
