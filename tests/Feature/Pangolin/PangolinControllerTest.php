<?php

use App\Jobs\Pangolin\FetchNewtConnections;
use App\Jobs\Pangolin\RunImport;
use App\Jobs\Pangolin\RunNormalize;
use App\Models\PangolinNewtAgent;
use App\Models\PangolinNewtConnection;
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

test('newt connections fetch creates a queued run and dispatches the job', function () {
    Queue::fake();
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('pangolin.newt-connections.fetch'))
        ->assertRedirect(route('pangolin.index', ['tab' => 'connections']));

    $run = PangolinRun::first();
    expect($run->type)->toBe('newt_connections');
    expect($run->status)->toBe('queued');
    Queue::assertPushed(FetchNewtConnections::class, 1);
});

test('the index page lists newt agents and filters the connections list by user', function () {
    $user = User::factory()->create();
    $agent = PangolinNewtAgent::factory()->create(['name' => 'patra']);
    PangolinNewtConnection::factory()->create([
        'newt_agent_id' => $agent->id, 'agent_name' => $agent->name, 'agent_ip' => $agent->ip,
        'user_name' => 'Kostas Tsouvalis', 'started_at' => now(),
    ]);
    PangolinNewtConnection::factory()->create([
        'newt_agent_id' => $agent->id, 'agent_name' => $agent->name, 'agent_ip' => $agent->ip,
        'user_name' => 'Someone Else', 'started_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('pangolin.index', ['tab' => 'connections', 'user' => 'Tsouvalis']));

    $response->assertOk();
    expect($response['newtAgents']->pluck('name'))->toContain('patra');
    expect($response['connections']->total())->toBe(1);
    expect($response['connections']->first()->user_name)->toBe('Kostas Tsouvalis');
});

test('connections pagination links keep the connections tab even when the page was loaded without ?tab=', function () {
    $user = User::factory()->create();
    $agent = PangolinNewtAgent::factory()->create();
    PangolinNewtConnection::factory()->count(30)->create([
        'newt_agent_id' => $agent->id, 'agent_name' => $agent->name, 'agent_ip' => $agent->ip,
    ]);

    $response = $this->actingAs($user)->get(route('pangolin.index'));

    expect($response['connections']->nextPageUrl())->toContain('tab=connections');
});

test('the connections list is filtered by resource name', function () {
    $user = User::factory()->create();
    $agent = PangolinNewtAgent::factory()->create();
    $wanted = PangolinNewtConnection::factory()->create([
        'newt_agent_id' => $agent->id, 'agent_name' => $agent->name, 'agent_ip' => $agent->ip,
        'resource_name' => 'patra-ktsouvalis-2302-50', 'started_at' => now(),
    ]);
    PangolinNewtConnection::factory()->create([
        'newt_agent_id' => $agent->id, 'agent_name' => $agent->name, 'agent_ip' => $agent->ip,
        'resource_name' => 'rustdesk-telefos', 'started_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('pangolin.index', ['tab' => 'connections', 'resource' => 'ktsouvalis']));

    expect($response['connections']->total())->toBe(1);
    expect($response['connections']->first()->id)->toBe($wanted->id);
});

test('the connections list is filtered by site, and the site dropdown is sourced from distinct site names on the connections themselves', function () {
    $user = User::factory()->create();
    $agent = PangolinNewtAgent::factory()->create();
    $wanted = PangolinNewtConnection::factory()->create([
        'newt_agent_id' => $agent->id, 'agent_name' => $agent->name, 'agent_ip' => $agent->ip,
        'site_name' => 'Patras', 'started_at' => now(),
    ]);
    PangolinNewtConnection::factory()->create([
        'newt_agent_id' => $agent->id, 'agent_name' => $agent->name, 'agent_ip' => $agent->ip,
        'site_name' => 'Kalamata', 'started_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('pangolin.index', ['tab' => 'connections', 'site' => 'Patras']));

    expect($response['sites']->all())->toEqualCanonicalizing(['Kalamata', 'Patras']);
    expect($response['connections']->total())->toBe(1);
    expect($response['connections']->first()->id)->toBe($wanted->id);
});

test('the from/to date filters treat the picked dates as Athens calendar days, not UTC ones', function () {
    $user = User::factory()->create();
    $agent = PangolinNewtAgent::factory()->create();

    // 2026-09-21 20:00 UTC is still 2026-09-21 23:00 in Athens (UTC+3, ahead
    // of UTC) — a naive string comparison against 'from=2026-09-22' would
    // wrongly exclude the row below (true Athens midnight) while a UTC-only
    // comparison against 'to=2026-09-22' would wrongly include this one.
    $stillAthensSept21 = PangolinNewtConnection::factory()->create([
        'newt_agent_id' => $agent->id, 'agent_name' => $agent->name, 'agent_ip' => $agent->ip,
        'started_at' => '2026-09-21 20:00:00',
    ]);
    // 2026-09-21 22:00 UTC is 2026-09-22 01:00 in Athens — must be included.
    $justAfterAthensMidnight = PangolinNewtConnection::factory()->create([
        'newt_agent_id' => $agent->id, 'agent_name' => $agent->name, 'agent_ip' => $agent->ip,
        'started_at' => '2026-09-21 22:00:00',
    ]);

    $response = $this->actingAs($user)->get(route('pangolin.index', [
        'tab' => 'connections', 'from' => '2026-09-22', 'to' => '2026-09-22',
    ]));

    $ids = $response['connections']->pluck('id');
    expect($ids)->not->toContain($stillAthensSept21->id);
    expect($ids)->toContain($justAfterAthensMidnight->id);
});

test('started_at_local converts the stored UTC timestamp to Europe/Athens', function () {
    $connection = PangolinNewtConnection::factory()->create(['started_at' => '2026-09-22 10:00:00']);

    expect($connection->started_at_local->format('Y-m-d H:i:s'))->toBe('2026-09-22 13:00:00');
    // The original UTC value is untouched by reading the local accessor.
    expect($connection->started_at->format('Y-m-d H:i:s'))->toBe('2026-09-22 10:00:00');
});
