<?php

use App\Models\NetworkArpHistory;
use App\Models\NetworkDevice;
use App\Models\NetworkDevicePort;
use App\Models\NetworkMacHistory;
use App\Models\User;

beforeEach(function () {
    enableMenu('network-lookup.*');
    $this->user = User::factory()->create();
});

test('searching by IP resolves through arp history to the latest mac history row', function () {
    $device = NetworkDevice::factory()->create(['name' => 'Karam_SW_1']);

    NetworkArpHistory::create([
        'ip_address' => '10.23.14.156',
        'mac_address' => '20:3a:43:16:6c:90',
        'vlan' => '2313',
        'first_seen_at' => now()->subDays(10),
        'last_seen_at' => now()->subDays(10),
    ]);

    // Older location for the same mac - must not be picked as "current".
    NetworkMacHistory::create([
        'network_device_id' => $device->id,
        'mac_address' => '20:3a:43:16:6c:90',
        'port' => 'GE0/0/1',
        'vlan' => '2313',
        'first_seen_at' => now()->subDays(20),
        'last_seen_at' => now()->subDays(15),
    ]);

    NetworkMacHistory::create([
        'network_device_id' => $device->id,
        'mac_address' => '20:3a:43:16:6c:90',
        'port' => 'GE0/0/3',
        'vlan' => '2313',
        'first_seen_at' => now()->subDays(14),
        'last_seen_at' => now(),
    ]);

    $response = $this->actingAs($this->user)->get(route('network-lookup.index', ['q' => '10.23.14.156']));

    $response->assertOk();
    $response->assertViewHas('result', function ($result) use ($device) {
        return $result['mac_address'] === '20:3a:43:16:6c:90'
            && $result['device']->is($device)
            && $result['port'] === 'GE0/0/3';
    });
    $response->assertViewHas('macHistory', fn ($history) => $history->count() === 2);
});

test('searching by MAC in any input format resolves the current switch/port and known IP', function () {
    $device = NetworkDevice::factory()->create();

    NetworkMacHistory::create([
        'network_device_id' => $device->id,
        'mac_address' => '20:3a:43:16:6c:90',
        'port' => 'GE0/0/3',
        'vlan' => '2313',
        'first_seen_at' => now()->subDay(),
        'last_seen_at' => now(),
    ]);

    NetworkArpHistory::create([
        'ip_address' => '10.23.14.156',
        'mac_address' => '20:3a:43:16:6c:90',
        'vlan' => '2313',
        'first_seen_at' => now()->subDay(),
        'last_seen_at' => now(),
    ]);

    $response = $this->actingAs($this->user)->get(route('network-lookup.index', ['q' => '203a-4316-6c90']));

    $response->assertOk();
    $response->assertViewHas('result', fn ($result) => $result['ip_address'] === '10.23.14.156' && $result['port'] === 'GE0/0/3');
});

test('the current-location result carries the mac in Huawei display format alongside the canonical one', function () {
    $device = NetworkDevice::factory()->create();

    NetworkMacHistory::create([
        'network_device_id' => $device->id,
        'mac_address' => '20:3a:43:16:6c:90',
        'port' => 'GE0/0/3',
        'vlan' => '2313',
        'first_seen_at' => now(),
        'last_seen_at' => now(),
    ]);

    $response = $this->actingAs($this->user)->get(route('network-lookup.index', ['q' => '203a-4316-6c90']));

    $response->assertOk();
    $response->assertViewHas('result', fn ($result) => $result['mac_address'] === '20:3a:43:16:6c:90'
        && $result['mac_address_display'] === '203a-4316-6c90');
});

test('a stale trunk-port history row is never shown, even if it is the most recent row for that mac', function () {
    $device = NetworkDevice::factory()->create();
    NetworkDevicePort::create(['network_device_id' => $device->id, 'port' => 'GE0/0/1', 'link_type' => 'trunk']);

    // The real access-port sighting, older...
    NetworkMacHistory::create([
        'network_device_id' => $device->id,
        'mac_address' => '20:3a:43:16:6c:90',
        'port' => 'GE0/0/3',
        'vlan' => '2313',
        'first_seen_at' => now()->subDays(5),
        'last_seen_at' => now()->subDays(5),
    ]);

    // ...but a stale trunk-port row (recorded before link_type was known,
    // or before this exclusion existed) is more recent and must not win.
    NetworkMacHistory::create([
        'network_device_id' => $device->id,
        'mac_address' => '20:3a:43:16:6c:90',
        'port' => 'GE0/0/1',
        'vlan' => '2313',
        'first_seen_at' => now()->subDay(),
        'last_seen_at' => now(),
    ]);

    $response = $this->actingAs($this->user)->get(route('network-lookup.index', ['q' => '203a-4316-6c90']));

    $response->assertOk();
    $response->assertViewHas('result', fn ($result) => $result['port'] === 'GE0/0/3');
    $response->assertViewHas('macHistory', fn ($history) => $history->count() === 1 && $history->first()->port === 'GE0/0/3');
});

test('a mac only ever seen on a trunk port resolves as not found, not as the trunk location', function () {
    $device = NetworkDevice::factory()->create();
    NetworkDevicePort::create(['network_device_id' => $device->id, 'port' => 'GE0/0/1', 'link_type' => 'trunk']);

    NetworkMacHistory::create([
        'network_device_id' => $device->id,
        'mac_address' => '20:3a:43:16:6c:90',
        'port' => 'GE0/0/1',
        'vlan' => '2313',
        'first_seen_at' => now(),
        'last_seen_at' => now(),
    ]);

    $response = $this->actingAs($this->user)->get(route('network-lookup.index', ['q' => '203a-4316-6c90']));

    $response->assertOk();
    $response->assertViewHas('result', null);
});

test('an unrecognized query string shows a validation error rather than a false not-found', function () {
    $response = $this->actingAs($this->user)->get(route('network-lookup.index', ['q' => 'not-an-ip-or-mac']));

    $response->assertOk();
    $response->assertViewHas('searchError');
    $response->assertViewHas('result', null);
});

test('a valid IP with no arp history returns not found rather than an error', function () {
    $response = $this->actingAs($this->user)->get(route('network-lookup.index', ['q' => '10.23.99.99']));

    $response->assertOk();
    $response->assertViewHas('searchError', null);
    $response->assertViewHas('result', null);
});
