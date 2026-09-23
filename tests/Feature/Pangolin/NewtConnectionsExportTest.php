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
    expect($sheet->getCell('C2')->getValue())->toBe('Kostas Tsouvalis');
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
