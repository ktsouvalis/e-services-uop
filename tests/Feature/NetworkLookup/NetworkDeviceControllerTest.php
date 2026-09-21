<?php

use App\Models\NetworkDevice;
use App\Models\NetworkDevicePort;
use App\Models\User;

beforeEach(function () {
    enableMenu('network-lookup.*');
    $this->user = User::factory()->create();

    // Point the registry at a throwaway file so tests never touch the real
    // hand-maintained devices.json.
    $this->devicesFile = storage_path('app/private/network-lookup/test-crud-devices.json');
    @mkdir(dirname($this->devicesFile), 0777, true);
    file_put_contents($this->devicesFile, '[]');
    config(['network-lookup.devices_file' => $this->devicesFile]);
});

afterEach(function () {
    @unlink($this->devicesFile);
});

test('storing a new device creates it in the DB and appends it to devices.json', function () {
    $this->actingAs($this->user)->post(route('network-lookup.devices.store'), [
        'name' => 'New_SW_1',
        'mgmt_ip' => '10.23.255.99',
        'vendor' => 'huawei',
        'role' => 'l2',
        'protocol' => 'ssh',
    ])->assertRedirect(route('network-lookup.index'));

    expect(NetworkDevice::where('name', 'New_SW_1')->exists())->toBeTrue();

    $entries = json_decode(file_get_contents($this->devicesFile), true);
    expect($entries)->toHaveCount(1);
    expect($entries[0])->toBe([
        'name' => 'New_SW_1',
        'ip' => '10.23.255.99',
        'vendor' => 'huawei',
        'role' => 'l2',
        'protocol' => 'ssh',
    ]);
});

test('storing a device with trunk_ports marks those ports link_type=trunk and writes the csv to devices.json', function () {
    $this->actingAs($this->user)->post(route('network-lookup.devices.store'), [
        'name' => 'New_SW_1',
        'mgmt_ip' => '10.23.255.99',
        'vendor' => 'huawei',
        'role' => 'l2',
        'protocol' => 'ssh',
        'trunk_ports' => 'XGE0/0/1, GE0/0/24',
    ])->assertRedirect(route('network-lookup.index'));

    $device = NetworkDevice::where('name', 'New_SW_1')->first();
    $trunkPorts = NetworkDevicePort::where('network_device_id', $device->id)->where('link_type', 'trunk')->pluck('port');
    expect($trunkPorts->sort()->values()->all())->toBe(['GE0/0/24', 'XGE0/0/1']);

    $entries = json_decode(file_get_contents($this->devicesFile), true);
    expect($entries[0]['trunk_ports'])->toBe('XGE0/0/1, GE0/0/24');
});

test('updating a device to remove a trunk port from the list demotes it back to unclassified', function () {
    $device = NetworkDevice::factory()->create(['name' => 'Karam_SW_1']);
    NetworkDevicePort::create(['network_device_id' => $device->id, 'port' => 'XGE0/0/1', 'link_type' => 'trunk']);
    NetworkDevicePort::create(['network_device_id' => $device->id, 'port' => 'GE0/0/24', 'link_type' => 'trunk']);

    $this->actingAs($this->user)->put(route('network-lookup.devices.update', $device), [
        'name' => 'Karam_SW_1',
        'mgmt_ip' => $device->mgmt_ip,
        'vendor' => 'huawei',
        'role' => 'l2',
        'protocol' => 'ssh',
        'trunk_ports' => 'XGE0/0/1',
    ])->assertRedirect(route('network-lookup.index'));

    expect(NetworkDevicePort::where('network_device_id', $device->id)->where('port', 'XGE0/0/1')->value('link_type'))->toBe('trunk');
    expect(NetworkDevicePort::where('network_device_id', $device->id)->where('port', 'GE0/0/24')->value('link_type'))->toBeNull();
});

test('the edit form is pre-filled with the device current trunk ports', function () {
    $device = NetworkDevice::factory()->create();
    NetworkDevicePort::create(['network_device_id' => $device->id, 'port' => 'XGE0/0/1', 'link_type' => 'trunk']);

    $response = $this->actingAs($this->user)->get(route('network-lookup.devices.edit', $device));

    $response->assertOk();
    $response->assertViewHas('trunkPorts', 'XGE0/0/1');
});

test('a duplicate device name is rejected', function () {
    NetworkDevice::factory()->create(['name' => 'Existing_SW']);

    $this->actingAs($this->user)->post(route('network-lookup.devices.store'), [
        'name' => 'Existing_SW',
        'mgmt_ip' => '10.23.255.5',
        'vendor' => 'huawei',
        'role' => 'l2',
        'protocol' => 'ssh',
    ])->assertSessionHasErrors('name');

    expect(NetworkDevice::where('name', 'Existing_SW')->count())->toBe(1);
});

test('updating a device (including a rename) updates the DB row and replaces its devices.json entry in place', function () {
    $device = NetworkDevice::factory()->create(['name' => 'Old_Name', 'mgmt_ip' => '10.23.255.1']);
    file_put_contents($this->devicesFile, json_encode([
        ['name' => 'Old_Name', 'ip' => '10.23.255.1', 'vendor' => 'huawei', 'role' => 'l2', 'protocol' => 'ssh'],
        ['name' => 'Other_SW', 'ip' => '10.23.255.2', 'vendor' => 'huawei', 'role' => 'l2', 'protocol' => 'ssh'],
    ]));

    $this->actingAs($this->user)->put(route('network-lookup.devices.update', $device), [
        'name' => 'New_Name',
        'mgmt_ip' => '10.23.255.50',
        'vendor' => 'cisco',
        'role' => 'l2',
        'protocol' => 'telnet',
    ])->assertRedirect(route('network-lookup.index'));

    $device->refresh();
    expect($device->name)->toBe('New_Name');
    expect($device->mgmt_ip)->toBe('10.23.255.50');
    expect($device->vendor)->toBe('cisco');
    expect($device->protocol)->toBe('telnet');

    $entries = json_decode(file_get_contents($this->devicesFile), true);
    expect($entries)->toHaveCount(2); // replaced in place, not appended
    $names = array_column($entries, 'name');
    expect($names)->toContain('New_Name');
    expect($names)->not->toContain('Old_Name');
});

test('destroying a device removes it from both the DB and devices.json', function () {
    $device = NetworkDevice::factory()->create(['name' => 'To_Remove']);
    file_put_contents($this->devicesFile, json_encode([
        ['name' => 'To_Remove', 'ip' => '10.23.255.1', 'vendor' => 'huawei', 'role' => 'l2', 'protocol' => 'ssh'],
        ['name' => 'Keep_Me', 'ip' => '10.23.255.2', 'vendor' => 'huawei', 'role' => 'l2', 'protocol' => 'ssh'],
    ]));

    $this->actingAs($this->user)->delete(route('network-lookup.devices.destroy', $device))
        ->assertRedirect(route('network-lookup.index'));

    expect(NetworkDevice::find($device->id))->toBeNull();

    $entries = json_decode(file_get_contents($this->devicesFile), true);
    expect($entries)->toHaveCount(1);
    expect($entries[0]['name'])->toBe('Keep_Me');
});

test('guests are redirected to login rather than reaching device CRUD routes unauthenticated', function () {
    $device = NetworkDevice::factory()->create();

    $this->get(route('network-lookup.devices.create'))->assertRedirect(route('login'));
    $this->post(route('network-lookup.devices.store'))->assertRedirect(route('login'));
    $this->get(route('network-lookup.devices.edit', $device))->assertRedirect(route('login'));
    $this->put(route('network-lookup.devices.update', $device))->assertRedirect(route('login'));
    $this->delete(route('network-lookup.devices.destroy', $device))->assertRedirect(route('login'));
});
