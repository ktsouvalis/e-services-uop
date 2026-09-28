<?php

use App\Models\PangolinNewtAgent;
use App\Models\PangolinNewtConnection;
use App\Models\User;
use PhpOffice\PhpSpreadsheet\IOFactory;

beforeEach(function () {
    enableMenu('pangolin');
});

function newtExportFilename(string $format): string
{
    return storage_path('app/private/pangolin/exports/newt-connections-'.now()->format('Y-m-d').".{$format}");
}

test('exporting with no filters includes every connection', function () {
    $user = User::factory()->create();
    $agent = PangolinNewtAgent::factory()->create();
    PangolinNewtConnection::factory()->create([
        'newt_agent_id' => $agent->id, 'agent_name' => $agent->name, 'agent_ip' => $agent->ip,
        'user_name' => 'Kostas Tsouvalis', 'resource_name' => 'patra-ktsouvalis-2302-50',
    ]);
    PangolinNewtConnection::factory()->create([
        'newt_agent_id' => $agent->id, 'agent_name' => $agent->name, 'agent_ip' => $agent->ip,
        'user_name' => 'Someone Else', 'resource_name' => 'rustdesk-telefos',
    ]);

    $response = $this->actingAs($user)->get(route('pangolin.newt-connections.export', ['format' => 'xlsx']));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('spreadsheetml');

    $path = newtExportFilename('xlsx');
    $sheet = IOFactory::load($path)->getActiveSheet();
    expect($sheet->getHighestRow())->toBe(3); // header + 2 rows
    @unlink($path);
});

test('exporting with filters only includes the matching connections', function () {
    $user = User::factory()->create();
    $agent = PangolinNewtAgent::factory()->create();
    PangolinNewtConnection::factory()->create([
        'newt_agent_id' => $agent->id, 'agent_name' => $agent->name, 'agent_ip' => $agent->ip,
        'user_name' => 'Kostas Tsouvalis', 'resource_name' => 'patra-ktsouvalis-2302-50',
    ]);
    PangolinNewtConnection::factory()->create([
        'newt_agent_id' => $agent->id, 'agent_name' => $agent->name, 'agent_ip' => $agent->ip,
        'user_name' => 'Someone Else', 'resource_name' => 'rustdesk-telefos',
    ]);

    $response = $this->actingAs($user)->get(route('pangolin.newt-connections.export', ['format' => 'xlsx', 'user' => 'Tsouvalis']));

    $response->assertOk();
    $path = newtExportFilename('xlsx');
    $sheet = IOFactory::load($path)->getActiveSheet();
    expect($sheet->getHighestRow())->toBe(2); // header + 1 matching row
    expect($sheet->getCell('E2')->getValue())->toBe('Kostas Tsouvalis');
    @unlink($path);
});

/** $count back-to-back flows from one user to one destination, 1s apart. */
function newtBurst(PangolinNewtAgent $agent, int $count, array $overrides = []): void
{
    foreach (range(0, $count - 1) as $i) {
        PangolinNewtConnection::factory()->create([
            'newt_agent_id' => $agent->id, 'agent_name' => $agent->name, 'agent_ip' => $agent->ip,
            'user_name' => 'Kostas Tsouvalis', 'src_ip' => '100.90.0.5', 'dst_ip' => '10.23.2.50', 'dst_port' => '22',
            'resource_id' => 35, 'site_name' => 'Patras', 'resource_name' => 'patra-ktsouvalis-2302-50',
            'started_at' => now()->subHour()->addSeconds($i * 2),
            'ended_at' => now()->subHour()->addSeconds($i * 2 + 1),
            ...$overrides,
        ]);
    }
}

test('export writes consolidated sessions with connection count and status columns', function () {
    $user = User::factory()->create();
    $agent = PangolinNewtAgent::factory()->create();
    newtBurst($agent, 5);
    newtBurst($agent, 3, ['dst_port' => '3389', 'failed' => true, 'failure_reason' => 'timeout']);

    $this->actingAs($user)->get(route('pangolin.newt-connections.export', ['format' => 'xlsx']))->assertOk();

    $path = newtExportFilename('xlsx');
    $rows = IOFactory::load($path)->getActiveSheet()->toArray();
    @unlink($path);

    expect($rows[0])->toBe(['Started (Athens time)', 'Duration', 'Connections', 'Status', 'Who', 'Client', 'Sites', 'Resource', 'Destination']);
    expect($rows)->toHaveCount(3); // header + 2 logical sessions
    $byDestination = collect(array_slice($rows, 1))->keyBy(8);
    expect($byDestination['10.23.2.50:22'][2])->toEqual(5);
    expect($byDestination['10.23.2.50:22'][3])->toBe('OK');
    expect($byDestination['10.23.2.50:3389'][2])->toEqual(3);
    expect($byDestination['10.23.2.50:3389'][3])->toBe('Failed: timeout');
    expect($byDestination['10.23.2.50:3389'][1])->toStartWith('3 attempts, ');
});

test('export honours the raw flows toggle and the status filter', function () {
    $user = User::factory()->create();
    $agent = PangolinNewtAgent::factory()->create();
    newtBurst($agent, 5);
    newtBurst($agent, 3, ['dst_port' => '3389', 'failed' => true, 'failure_reason' => 'refused']);

    $this->actingAs($user)->get(route('pangolin.newt-connections.export', ['format' => 'xlsx', 'raw' => 1]))->assertOk();
    $path = newtExportFilename('xlsx');
    expect(IOFactory::load($path)->getActiveSheet()->getHighestRow())->toBe(9); // header + 8 flows
    @unlink($path);

    $this->actingAs($user)->get(route('pangolin.newt-connections.export', ['format' => 'xlsx', 'status' => 'failed']))->assertOk();
    $sheet = IOFactory::load($path)->getActiveSheet();
    expect($sheet->getHighestRow())->toBe(2);
    expect($sheet->getCell('D2')->getValue())->toBe('Failed: refused');
    @unlink($path);
});

test('exporting as ods downloads an ods spreadsheet', function () {
    $user = User::factory()->create();
    $agent = PangolinNewtAgent::factory()->create();
    PangolinNewtConnection::factory()->create([
        'newt_agent_id' => $agent->id, 'agent_name' => $agent->name, 'agent_ip' => $agent->ip,
    ]);

    $response = $this->actingAs($user)->get(route('pangolin.newt-connections.export', ['format' => 'ods']));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('opendocument');
    @unlink(newtExportFilename('ods'));
});

test('an invalid format 404s', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('pangolin.newt-connections.export', ['format' => 'csv']))->assertNotFound();
});

test('export is menu-gated and requires auth like the rest of the pangolin routes', function () {
    $this->get(route('pangolin.newt-connections.export'))->assertRedirect(route('login'));

    disableMenu('pangolin');
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('pangolin.newt-connections.export'))->assertForbidden();
});
