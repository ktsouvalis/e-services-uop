<?php

use App\Models\NetworkDevice;
use App\Models\User;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    enableMenu('network-lookup.*');
    $this->user = User::factory()->create();

    $this->devicesFile = storage_path('app/private/network-lookup/test-import-devices.json');
    @mkdir(dirname($this->devicesFile), 0777, true);
    file_put_contents($this->devicesFile, '[]');
    config(['network-lookup.devices_file' => $this->devicesFile]);
});

afterEach(function () {
    @unlink($this->devicesFile);
});

function uploadDevicesJson(array $entries): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'devices').'.json';
    file_put_contents($path, json_encode($entries));

    return new UploadedFile($path, 'devices.json', 'application/json', null, true);
}

test('importing a file creates new devices and updates existing ones by name, in both the DB and devices.json', function () {
    $existing = NetworkDevice::factory()->create(['name' => 'Existing_SW', 'mgmt_ip' => '10.23.255.1', 'enabled' => false]);

    $file = uploadDevicesJson([
        ['name' => 'Existing_SW', 'ip' => '10.23.255.99', 'vendor' => 'cisco', 'role' => 'l2', 'protocol' => 'telnet'],
        ['name' => 'Brand_New_SW', 'ip' => '10.23.255.5', 'vendor' => 'huawei', 'role' => 'l2', 'protocol' => 'ssh'],
    ]);

    $this->actingAs($this->user)->post(route('network-lookup.devices.import'), ['file' => $file])
        ->assertRedirect(route('network-lookup.index'));

    $existing->refresh();
    expect($existing->mgmt_ip)->toBe('10.23.255.99');
    expect($existing->vendor)->toBe('cisco');
    expect($existing->enabled)->toBeFalse(); // untouched, like sync-devices

    expect(NetworkDevice::where('name', 'Brand_New_SW')->exists())->toBeTrue();

    $entries = json_decode(file_get_contents($this->devicesFile), true);
    expect($entries)->toHaveCount(2);
});

test('invalid entries in the uploaded file are skipped, valid ones still import', function () {
    $file = uploadDevicesJson([
        ['name' => 'Good_SW', 'ip' => '10.23.255.5', 'vendor' => 'huawei', 'role' => 'l2', 'protocol' => 'ssh'],
        ['name' => 'Bad_SW', 'ip' => 'not-an-ip', 'vendor' => 'huawei', 'role' => 'l2'],
    ]);

    $this->actingAs($this->user)->post(route('network-lookup.devices.import'), ['file' => $file])
        ->assertRedirect(route('network-lookup.index'));

    expect(NetworkDevice::where('name', 'Good_SW')->exists())->toBeTrue();
    expect(NetworkDevice::where('name', 'Bad_SW')->exists())->toBeFalse();
});

test('a file that is not a JSON array is rejected with an error, nothing is imported', function () {
    $path = tempnam(sys_get_temp_dir(), 'devices').'.json';
    file_put_contents($path, 'not json at all');
    $file = new UploadedFile($path, 'devices.json', 'application/json', null, true);

    $this->actingAs($this->user)->post(route('network-lookup.devices.import'), ['file' => $file])
        ->assertRedirect();

    expect(NetworkDevice::count())->toBe(0);
});

test('guests are redirected to login rather than reaching the import routes unauthenticated', function () {
    $this->get(route('network-lookup.devices.import.show'))->assertRedirect(route('login'));
    $this->post(route('network-lookup.devices.import'))->assertRedirect(route('login'));
});
