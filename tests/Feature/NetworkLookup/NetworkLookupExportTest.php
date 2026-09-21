<?php

use App\Models\NetworkDevice;
use App\Models\NetworkMacHistory;
use App\Models\User;

beforeEach(function () {
    enableMenu('network-lookup.*');
    $this->user = User::factory()->create();
});

test('exporting a matched query downloads an xlsx spreadsheet', function () {
    $device = NetworkDevice::factory()->create();
    NetworkMacHistory::create([
        'network_device_id' => $device->id,
        'mac_address' => '20:3a:43:16:6c:90',
        'port' => 'GE0/0/3',
        'vlan' => '2313',
        'first_seen_at' => now(),
        'last_seen_at' => now(),
    ]);

    $response = $this->actingAs($this->user)->get(route('network-lookup.export', ['q' => '203a-4316-6c90', 'format' => 'xlsx']));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('spreadsheetml');

    // deleteFileAfterSend()'s terminating callback doesn't run under the test
    // HTTP kernel - clean up rather than leaving the file behind.
    @unlink(storage_path('app/private/network-lookup/exports/network-lookup-203a-4316-6c90.xlsx'));
});

test('exporting as ods downloads an ods spreadsheet', function () {
    $device = NetworkDevice::factory()->create();
    NetworkMacHistory::create([
        'network_device_id' => $device->id,
        'mac_address' => '20:3a:43:16:6c:90',
        'port' => 'GE0/0/3',
        'vlan' => '2313',
        'first_seen_at' => now(),
        'last_seen_at' => now(),
    ]);

    $response = $this->actingAs($this->user)->get(route('network-lookup.export', ['q' => '203a-4316-6c90', 'format' => 'ods']));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('opendocument');

    @unlink(storage_path('app/private/network-lookup/exports/network-lookup-203a-4316-6c90.ods'));
});

test('exporting without a query 404s instead of generating an empty file', function () {
    $this->actingAs($this->user)->get(route('network-lookup.export'))->assertNotFound();
});

test('exporting an invalid query redirects back with the same validation error the search shows', function () {
    $response = $this->actingAs($this->user)->get(route('network-lookup.export', ['q' => 'not-an-ip-or-mac']));

    $response->assertRedirect();
    $response->assertSessionHas('error');
});
