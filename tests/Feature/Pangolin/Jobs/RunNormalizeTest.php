<?php

use App\Jobs\Pangolin\RunNormalize;
use App\Models\PangolinRun;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function () {
    config([
        'pangolin.nodes' => [],
        'pangolin.newt.hosts' => [],
        'pangolin.vip' => null,
        'pangolin.base_url' => 'https://pangolin.test',
        'pangolin.org_slug' => 'uop',
        'pangolin.api_key' => 'test-key',
    ]);
    // Run directories are named after the PangolinRun id, and sqlite's
    // :memory: RefreshDatabase rolls back per test rather than recreating the
    // schema — ids restart from 1 every test, so leftover files from a
    // previous test's run would otherwise be inherited by this one.
    File::deleteDirectory(storage_path('app/private/pangolin'));
});

function fakePangolinSiteResourcesEndpoint(array $resources): void
{
    Http::fake([
        'https://pangolin.test/v1/org/uop/site-resources*' => Http::response([
            'data' => [
                'siteResources' => $resources,
                'pagination' => ['total' => count($resources)],
            ],
        ], 200),
        '*' => Http::response([], 200),
    ]);
}

test('refuses to run unscoped and fails loudly when every requested resource id fails to resolve', function () {
    fakePangolinSiteResourcesEndpoint([]);
    Process::fake();

    $run = PangolinRun::factory()->create(['type' => 'normalize', 'options' => [
        'apply' => true,
        'resource_ids' => ['typo-niceid'],
    ]]);

    RunNormalize::dispatch($run);

    $run->refresh();
    expect($run->status)->toBe('failed');
    expect($run->error)->toContain('refusing to run unscoped');
    Process::assertNothingRan();
});

test('a dry run (no resource_ids requested) is allowed to run unscoped', function () {
    fakePangolinSiteResourcesEndpoint([]);
    Process::fake(function ($process) {
        touch($process->path.'/report.xlsx');

        return Process::result(exitCode: 0);
    });

    $run = PangolinRun::factory()->create(['type' => 'normalize', 'options' => [
        'apply' => false,
        'resource_ids' => [],
    ]]);

    RunNormalize::dispatch($run);

    expect($run->fresh()->status)->toBe('completed');
    Process::assertRan(fn ($process) => ! in_array('--resource-id', (array) $process->command, true));
});

test('resolves a niceId to its numeric siteResourceId and passes it as --resource-id, and adds --apply --yes when applying', function () {
    fakePangolinSiteResourcesEndpoint([
        ['siteResourceId' => 555, 'niceId' => 'mkatsis-2302-50-p22-p3389'],
    ]);
    $capturedCommand = null;
    Process::fake(function ($process) use (&$capturedCommand) {
        $capturedCommand = $process->command;

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Normalize Report');
        $sheet->setCellValue('A1', 'Resource');
        $sheet->setCellValue('B1', 'Status');
        $sheet->setCellValue('A2', 'mkatsis-2302-50-p22-p3389');
        $sheet->setCellValue('B2', 'RENAMED');
        (new Xlsx($spreadsheet))->save($process->path.'/report.xlsx');

        return Process::result(exitCode: 0);
    });

    $run = PangolinRun::factory()->create(['type' => 'normalize', 'options' => [
        'apply' => true,
        'resource_ids' => ['mkatsis-2302-50-p22-p3389'],
    ]]);

    RunNormalize::dispatch($run);

    $run->refresh();
    expect($run->status)->toBe('completed');
    expect($run->summary)->toBe(['RENAMED' => 1]);
    expect($capturedCommand)->toContain('--apply', '--yes', '--resource-id', '555');
});

test('a failed script run marks the run failed', function () {
    fakePangolinSiteResourcesEndpoint([]);
    Process::fake(fn () => Process::result(errorOutput: 'boom', exitCode: 2));

    $run = PangolinRun::factory()->create(['type' => 'normalize', 'options' => ['apply' => false, 'resource_ids' => []]]);

    RunNormalize::dispatch($run);

    $run->refresh();
    expect($run->status)->toBe('failed');
    expect($run->error)->toBe('Exit code 2');
});
