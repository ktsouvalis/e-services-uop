<?php

use App\Jobs\Pangolin\RunImport;
use App\Models\PangolinRun;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use romanzipp\QueueMonitor\Models\Monitor;

beforeEach(function () {
    config([
        'pangolin.nodes' => [],
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

/**
 * A "Requests" sheet with one row: city "patra", destination 10.23.2.50,
 * ports "22,3389", no alias, two emails (one resolves to a known org user,
 * one doesn't), a note. Mirrors pangolin_private_resources_template.xlsx's
 * column order: Name, Destination, Ports, Alias, User Emails, Notes.
 */
function makePangolinImportInputFile(): string
{
    $path = storage_path('app/private/pangolin/imports/'.uniqid('input', true).'.xlsx');
    File::ensureDirectoryExists(dirname($path));

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Requests');
    $sheet->fromArray(['Name', 'Destination', 'Ports', 'Alias', 'User Emails', 'Notes'], null, 'A1');
    $sheet->fromArray(['patra', '10.23.2.50', '22,3389', null, 'ktsouvalis@uop.gr,unknown@uop.gr', 'a note'], null, 'A2');
    (new Xlsx($spreadsheet))->save($path);

    return $path;
}

function fakePangolinIntegrationApi(): void
{
    Http::fake([
        'https://pangolin.test/v1/org/uop' => Http::response(['data' => ['name' => 'UoP']], 200),
        'https://pangolin.test/v1/org/uop/sites*' => Http::response([
            'data' => ['sites' => [['siteId' => 5, 'name' => 'Patra Site', 'online' => true]], 'pagination' => ['total' => 1]],
        ], 200),
        'https://pangolin.test/v1/org/uop/users*' => Http::response([
            'data' => ['users' => [['id' => 42, 'email' => 'ktsouvalis@uop.gr']], 'pagination' => ['total' => 1]],
        ], 200),
        'https://pangolin.test/v1/org/uop/site-resource' => Http::sequence()
            ->push(['data' => ['siteResourceId' => 100, 'niceId' => 'ktsouvalis-2302-50-p22-p3389']], 200)
            ->push(['data' => ['siteResourceId' => 101, 'niceId' => 'unknown-2302-50-p22-p3389']], 200),
        'https://pangolin.test/v1/site-resource/*' => Http::response([], 200),
    ]);
}

test('a successful import creates one resource per resolved email, writes a Results sheet, and cleans up the upload', function () {
    fakePangolinIntegrationApi();
    $inputPath = makePangolinImportInputFile();
    $run = PangolinRun::factory()->create(['type' => 'import', 'input_path' => $inputPath]);

    RunImport::dispatch($run);

    $run->refresh();
    expect($run->status)->toBe('completed');
    expect($run->report_path)->toContain('input_results_');
    expect($run->summary)->toBe(['OK' => 1, 'OK_NO_USER' => 1]);

    // The uploaded original under pangolin/imports/ must not be left behind
    // now that a copy lives in the run's own directory.
    expect(File::exists($inputPath))->toBeFalse();

    Http::assertSent(fn ($request) => $request->url() === 'https://pangolin.test/v1/org/uop/site-resource'
        && $request->method() === 'PUT'
        && $request['name'] === 'patra-ktsouvalis-2302-50'
        && $request['niceId'] === 'ktsouvalis-2302-50-p22-p3389'
        && $request['userIds'] === [42]
        && ! array_key_exists('enabled', $request->data()));

    Http::assertSent(fn ($request) => $request->url() === 'https://pangolin.test/v1/site-resource/100'
        && $request->method() === 'POST' && $request['enabled'] === true);
    Http::assertSent(fn ($request) => $request->url() === 'https://pangolin.test/v1/site-resource/101'
        && $request->method() === 'POST' && $request['enabled'] === false);

    $monitor = Monitor::where('name', RunImport::class)->first();
    expect($monitor)->not->toBeNull();
});

test('a dry run makes no create/update calls and reports DRY-RUN statuses', function () {
    fakePangolinIntegrationApi();
    $inputPath = makePangolinImportInputFile();
    $run = PangolinRun::factory()->create([
        'type' => 'import',
        'input_path' => $inputPath,
        'options' => ['dry_run' => true],
    ]);

    RunImport::dispatch($run);

    $run->refresh();
    expect($run->status)->toBe('completed');
    expect($run->summary)->toBe(['DRY-RUN' => 1, 'DRY-RUN_NO_USER' => 1]);

    Http::assertNotSent(fn ($request) => in_array($request->method(), ['PUT', 'POST'], true));
});

test('a sheet formatted down to the last Excel row only parses rows that hold data', function () {
    fakePangolinIntegrationApi();
    $inputPath = makePangolinImportInputFile();
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($inputPath);
    $spreadsheet->getSheetByName('Requests')->getStyle('A1048576')->getFont()->setBold(true);
    (new Xlsx($spreadsheet))->save($inputPath);
    expect(\PhpOffice\PhpSpreadsheet\IOFactory::load($inputPath)->getSheetByName('Requests')->getHighestRow())->toBe(1048576);

    $run = PangolinRun::factory()->create([
        'type' => 'import',
        'input_path' => $inputPath,
        'options' => ['dry_run' => true],
    ]);

    RunImport::dispatch($run);

    $run->refresh();
    expect($run->status)->toBe('completed');
    expect($run->summary)->toBe(['DRY-RUN' => 1, 'DRY-RUN_NO_USER' => 1]);
});

test('an unresolved email still creates its resource, disabled and with no user attached', function () {
    fakePangolinIntegrationApi();
    $inputPath = makePangolinImportInputFile();
    $run = PangolinRun::factory()->create(['type' => 'import', 'input_path' => $inputPath]);

    RunImport::dispatch($run);

    Http::assertSent(fn ($request) => $request->url() === 'https://pangolin.test/v1/org/uop/site-resource'
        && $request['name'] === 'patra-unknown-2302-50'
        && $request['userIds'] === []
        // No owner → no niceId sent; Pangolin keeps its own generated one
        // until Normalize finds the user.
        && ! array_key_exists('niceId', $request->data()));
});

test('an email not in the org falls back to the org user with the same sanitized username (dots/underscores stripped)', function () {
    Http::fake([
        'https://pangolin.test/v1/org/uop' => Http::response(['data' => ['name' => 'UoP']], 200),
        'https://pangolin.test/v1/org/uop/sites*' => Http::response([
            'data' => ['sites' => [['siteId' => 5, 'name' => 'Patra Site']], 'pagination' => ['total' => 1]],
        ], 200),
        'https://pangolin.test/v1/org/uop/users*' => Http::response([
            'data' => ['users' => [['id' => 77, 'email' => 'costas.p@uop.gr']], 'pagination' => ['total' => 1]],
        ], 200),
        'https://pangolin.test/v1/org/uop/site-resource' => Http::response(['data' => ['siteResourceId' => 100, 'niceId' => 'costasp-1529-201-p22']], 200),
        'https://pangolin.test/v1/site-resource/*' => Http::response([], 200),
    ]);
    $path = storage_path('app/private/pangolin/imports/'.uniqid('input', true).'.xlsx');
    File::ensureDirectoryExists(dirname($path));
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Requests');
    $sheet->fromArray(['Name', 'Destination', 'Ports', 'Alias', 'User Emails', 'Notes'], null, 'A1');
    $sheet->fromArray(['tripoli', '10.15.29.201', '22', null, 'costas_p@go.uop.gr', null], null, 'A2');
    (new Xlsx($spreadsheet))->save($path);
    $run = PangolinRun::factory()->create(['type' => 'import', 'input_path' => $path]);

    RunImport::dispatch($run);

    expect($run->fresh()->summary)->toBe(['OK' => 1]);
    Http::assertSent(fn ($request) => $request->url() === 'https://pangolin.test/v1/org/uop/site-resource'
        && $request['name'] === 'tripoli-costasp-1529-201'
        && $request['niceId'] === 'costasp-1529-201-p22'
        && $request['userIds'] === [77]);
    Http::assertSent(fn ($request) => $request->url() === 'https://pangolin.test/v1/site-resource/100'
        && $request['enabled'] === true);
});

test('an unreachable Integration API fails the run with a clear error, before touching the xlsx', function () {
    Http::fake(['https://pangolin.test/*' => Http::response('', 500)]);
    $inputPath = makePangolinImportInputFile();
    $run = PangolinRun::factory()->create(['type' => 'import', 'input_path' => $inputPath]);

    RunImport::dispatch($run);

    $run->refresh();
    expect($run->status)->toBe('failed');
    expect($run->error)->toContain('Pangolin Integration API');
    expect($run->report_path)->toBeNull();
});

test('a missing Requests sheet fails the run cleanly instead of throwing', function () {
    fakePangolinIntegrationApi();
    $path = storage_path('app/private/pangolin/imports/'.uniqid('input', true).'.xlsx');
    File::ensureDirectoryExists(dirname($path));
    $spreadsheet = new Spreadsheet();
    $spreadsheet->getActiveSheet()->setTitle('NotRequests');
    (new Xlsx($spreadsheet))->save($path);

    $run = PangolinRun::factory()->create(['type' => 'import', 'input_path' => $path]);

    RunImport::dispatch($run);

    expect($run->fresh()->status)->toBe('failed');
    expect($run->fresh()->error)->toContain('Requests');
});

test('invalid ports fail that row locally without calling the create endpoint', function () {
    fakePangolinIntegrationApi();
    $path = storage_path('app/private/pangolin/imports/'.uniqid('input', true).'.xlsx');
    File::ensureDirectoryExists(dirname($path));
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Requests');
    $sheet->fromArray(['Name', 'Destination', 'Ports', 'Alias', 'User Emails', 'Notes'], null, 'A1');
    $sheet->fromArray(['patra', '10.23.2.50', 'not-a-port', null, 'ktsouvalis@uop.gr', null], null, 'A2');
    (new Xlsx($spreadsheet))->save($path);

    $run = PangolinRun::factory()->create(['type' => 'import', 'input_path' => $path]);

    RunImport::dispatch($run);

    expect($run->fresh()->status)->toBe('completed');
    expect($run->fresh()->summary)->toBe(['FAIL' => 1]);
    Http::assertNotSent(fn ($request) => $request->method() === 'PUT');
});
