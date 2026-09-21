<?php

use App\Models\NetworkDevice;
use App\Models\NetworkDevicePort;
use Illuminate\Support\Facades\Artisan;

function writeDevicesFile(array $entries): string
{
    $path = storage_path('app/private/network-lookup/test-devices.json');
    @mkdir(dirname($path), 0777, true);
    file_put_contents($path, json_encode($entries));
    config(['network-lookup.devices_file' => $path]);

    return $path;
}

afterEach(function () {
    @unlink(storage_path('app/private/network-lookup/test-devices.json'));
});

test('syncing creates a device row per valid entry', function () {
    writeDevicesFile([
        ['name' => 'Karam_SW_1', 'ip' => '10.23.255.78', 'vendor' => 'huawei', 'role' => 'l2'],
        ['name' => 'KEDD_Central_S6730', 'ip' => '10.23.255.1', 'vendor' => 'huawei', 'role' => 'core'],
    ]);

    Artisan::call('network-lookup:sync-devices');

    expect(NetworkDevice::count())->toBe(2);
    $device = NetworkDevice::where('name', 'Karam_SW_1')->first();
    expect($device->mgmt_ip)->toBe('10.23.255.78');
    expect($device->role)->toBe('l2');
});

test('re-syncing updates ip/vendor/role but never overwrites a manually-set enabled flag', function () {
    writeDevicesFile([
        ['name' => 'Karam_SW_1', 'ip' => '10.23.255.78', 'vendor' => 'huawei', 'role' => 'l2'],
    ]);
    Artisan::call('network-lookup:sync-devices');

    $device = NetworkDevice::where('name', 'Karam_SW_1')->first();
    $device->update(['enabled' => false]);

    writeDevicesFile([
        ['name' => 'Karam_SW_1', 'ip' => '10.23.255.99', 'vendor' => 'huawei', 'role' => 'l2'],
    ]);
    Artisan::call('network-lookup:sync-devices');

    $device->refresh();
    expect($device->mgmt_ip)->toBe('10.23.255.99');
    expect($device->enabled)->toBeFalse();
});

test('a trunk_ports entry marks those ports link_type=trunk on the device', function () {
    writeDevicesFile([
        ['name' => 'Karam_SW_1', 'ip' => '10.23.255.78', 'vendor' => 'huawei', 'role' => 'l2', 'trunk_ports' => 'XGE0/0/1, GE0/0/24'],
    ]);

    Artisan::call('network-lookup:sync-devices');

    $device = NetworkDevice::where('name', 'Karam_SW_1')->first();
    $trunkPorts = NetworkDevicePort::where('network_device_id', $device->id)->where('link_type', 'trunk')->pluck('port');
    expect($trunkPorts->sort()->values()->all())->toBe(['GE0/0/24', 'XGE0/0/1']);
});

test('re-syncing without a previously-listed trunk port demotes it back to unclassified', function () {
    writeDevicesFile([
        ['name' => 'Karam_SW_1', 'ip' => '10.23.255.78', 'vendor' => 'huawei', 'role' => 'l2', 'trunk_ports' => 'XGE0/0/1, GE0/0/24'],
    ]);
    Artisan::call('network-lookup:sync-devices');
    $device = NetworkDevice::where('name', 'Karam_SW_1')->first();

    writeDevicesFile([
        ['name' => 'Karam_SW_1', 'ip' => '10.23.255.78', 'vendor' => 'huawei', 'role' => 'l2', 'trunk_ports' => 'XGE0/0/1'],
    ]);
    Artisan::call('network-lookup:sync-devices');

    expect(NetworkDevicePort::where('network_device_id', $device->id)->where('port', 'XGE0/0/1')->value('link_type'))->toBe('trunk');
    expect(NetworkDevicePort::where('network_device_id', $device->id)->where('port', 'GE0/0/24')->value('link_type'))->toBeNull();
});

test('an invalid entry is skipped instead of crashing the whole sync', function () {
    writeDevicesFile([
        ['name' => 'Karam_SW_1', 'ip' => '10.23.255.78', 'vendor' => 'huawei', 'role' => 'l2'],
        ['name' => 'Bad_Device', 'ip' => 'not-an-ip', 'vendor' => 'huawei', 'role' => 'l2'],
        ['name' => 'Another_Bad', 'ip' => '10.23.255.79', 'vendor' => 'juniper', 'role' => 'l2'],
    ]);

    Artisan::call('network-lookup:sync-devices');

    expect(NetworkDevice::count())->toBe(1);
    expect(NetworkDevice::first()->name)->toBe('Karam_SW_1');
});
