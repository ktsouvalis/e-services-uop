<?php

use App\Jobs\NetworkLookup\PollSwitchMacTable;
use App\Models\NetworkDevice;
use App\Models\NetworkDevicePort;
use App\Models\NetworkMacHistory;
use App\Services\NetworkLookup\SshCommandRunner;
use App\Services\NetworkLookup\TelnetCommandRunner;

// SSH2/telnet sockets are opened directly by SshCommandRunner/
// TelnetCommandRunner (no container mock point for the transport itself) -
// binding a fake to the concrete class is what makes this job testable at
// all. The job type-hints the concrete classes (not the shared
// DeviceCommandRunnerContract interface), since both a SSH and a telnet
// runner must be resolvable per job - see that interface's own docblock. So
// these fakes *extend* the real classes (not just implement the interface)
// to satisfy that concrete type-hint when bound via $this->app->instance().
function fakeSshRunner(string $output): SshCommandRunner
{
    return new class($output) extends SshCommandRunner {
        public function __construct(private readonly string $output) {}

        public function run(string $host, array $commands): string
        {
            return $this->output;
        }
    };
}

function fakeFailingSshRunner(string $message): SshCommandRunner
{
    return new class($message) extends SshCommandRunner {
        public function __construct(private readonly string $message) {}

        public function run(string $host, array $commands): string
        {
            throw new RuntimeException($this->message);
        }
    };
}

function fakeTelnetRunner(string $output): TelnetCommandRunner
{
    return new class($output) extends TelnetCommandRunner {
        public function __construct(private readonly string $output) {}

        public function run(string $host, array $commands): string
        {
            return $this->output;
        }
    };
}

test('a fresh poll creates one mac_history row per parsed entry', function () {
    $this->app->instance(SshCommandRunner::class, fakeSshRunner("203a-4316-6c90 2313/-/-         GE0/0/3             dynamic\n"));
    $device = NetworkDevice::factory()->create(['vendor' => 'huawei']);

    PollSwitchMacTable::dispatch($device);

    $row = NetworkMacHistory::first();
    expect($row->mac_address)->toBe('20:3a:43:16:6c:90');
    expect($row->port)->toBe('GE0/0/3');
    expect($row->vlan)->toBe('2313');
    expect($device->fresh()->last_poll_status)->toBe('ok');
});

test('polling twice with the same mac/port/vlan touches last_seen_at instead of inserting a new row', function () {
    $this->app->instance(SshCommandRunner::class, fakeSshRunner("203a-4316-6c90 2313/-/-         GE0/0/3             dynamic\n"));
    $device = NetworkDevice::factory()->create(['vendor' => 'huawei']);

    PollSwitchMacTable::dispatch($device);
    $firstRow = NetworkMacHistory::first();
    $firstSeenAt = $firstRow->first_seen_at;

    $this->travel(5)->minutes();
    PollSwitchMacTable::dispatch($device);

    expect(NetworkMacHistory::count())->toBe(1);
    $row = NetworkMacHistory::first();
    expect($row->first_seen_at->equalTo($firstSeenAt))->toBeTrue();
    expect($row->last_seen_at->isAfter($firstSeenAt))->toBeTrue();
});

test('a mac reappearing on the same device/port/vlan after a gap longer than stale_after_minutes opens a new stay instead of silently bridging it', function () {
    config(['network-lookup.stale_after_minutes' => 1440]);
    $this->app->instance(SshCommandRunner::class, fakeSshRunner("203a-4316-6c90 2313/-/-         GE0/0/3             dynamic\n"));
    $device = NetworkDevice::factory()->create(['vendor' => 'huawei']);

    PollSwitchMacTable::dispatch($device);
    $firstRow = NetworkMacHistory::first();

    // A multi-day absence - well beyond even a routine overnight/weekend
    // shutdown - before it reconnects to the exact same port.
    $this->travel(3)->days();
    PollSwitchMacTable::dispatch($device);

    expect(NetworkMacHistory::count())->toBe(2);
    $oldRow = NetworkMacHistory::find($firstRow->id);
    expect($oldRow->last_seen_at->equalTo($firstRow->last_seen_at))->toBeTrue(); // untouched, not bridged
    $newRow = NetworkMacHistory::where('id', '!=', $firstRow->id)->first();
    expect($newRow->first_seen_at->equalTo($newRow->last_seen_at))->toBeTrue();
    expect($newRow->first_seen_at->isAfter($firstRow->last_seen_at))->toBeTrue();
});

test('a mac reappearing on the same device/port/vlan after a routine overnight gap still bridges into the same stay', function () {
    config(['network-lookup.stale_after_minutes' => 1440]);
    $this->app->instance(SshCommandRunner::class, fakeSshRunner("203a-4316-6c90 2313/-/-         GE0/0/3             dynamic\n"));
    $device = NetworkDevice::factory()->create(['vendor' => 'huawei']);

    PollSwitchMacTable::dispatch($device);
    $firstRow = NetworkMacHistory::first();

    // Off overnight (e.g. 18:00 to 10:00 the next day) - well under a day.
    $this->travel(16)->hours();
    PollSwitchMacTable::dispatch($device);

    expect(NetworkMacHistory::count())->toBe(1);
    $row = NetworkMacHistory::first();
    expect($row->first_seen_at->equalTo($firstRow->first_seen_at))->toBeTrue();
    expect($row->last_seen_at->isAfter($firstRow->last_seen_at))->toBeTrue();
});

test('a mac moving to a different port opens a new history row instead of overwriting the old one', function () {
    $this->app->instance(SshCommandRunner::class, fakeSshRunner("203a-4316-6c90 2313/-/-         GE0/0/3             dynamic\n"));
    $device = NetworkDevice::factory()->create(['vendor' => 'huawei']);
    PollSwitchMacTable::dispatch($device);

    $this->app->instance(SshCommandRunner::class, fakeSshRunner("203a-4316-6c90 2313/-/-         GE0/0/7             dynamic\n"));
    $this->travel(5)->minutes();
    PollSwitchMacTable::dispatch($device);

    expect(NetworkMacHistory::count())->toBe(2);
    $ports = NetworkMacHistory::orderBy('id')->pluck('port')->all();
    expect($ports)->toBe(['GE0/0/3', 'GE0/0/7']);
});

test('a leftover row on another devices trunk port does not stop this devices own repeat sighting from deduping', function () {
    $this->app->instance(SshCommandRunner::class, fakeSshRunner("203a-4316-6c90 2313/-/-         GE0/0/3             dynamic\n"));
    $device = NetworkDevice::factory()->create(['vendor' => 'huawei']);
    PollSwitchMacTable::dispatch($device);
    $firstRow = NetworkMacHistory::first();

    // Simulates data written before this other port's link_type was known:
    // a more-recent row for the same mac, on a *different* device, on a
    // port now classified as trunk. Without excluding it from the "most
    // recent" comparison, it would make $device's own unchanged sighting
    // look like a fresh location every poll.
    $otherDevice = NetworkDevice::factory()->create(['vendor' => 'huawei']);
    NetworkDevicePort::create(['network_device_id' => $otherDevice->id, 'port' => 'XGE0/0/1', 'link_type' => 'trunk']);
    NetworkMacHistory::create([
        'network_device_id' => $otherDevice->id,
        'mac_address' => '20:3a:43:16:6c:90',
        'port' => 'XGE0/0/1',
        'vlan' => '2313',
        'first_seen_at' => now(),
        'last_seen_at' => now(),
    ]);

    $this->travel(5)->minutes();
    PollSwitchMacTable::dispatch($device);

    expect(NetworkMacHistory::count())->toBe(2);
    $row = NetworkMacHistory::find($firstRow->id);
    expect($row->last_seen_at->isAfter($firstRow->last_seen_at))->toBeTrue();
});

test('a mac learned on a known trunk port is excluded from history entirely', function () {
    $device = NetworkDevice::factory()->create(['vendor' => 'huawei']);
    NetworkDevicePort::create(['network_device_id' => $device->id, 'port' => 'GE0/0/1', 'link_type' => 'trunk']);

    $this->app->instance(SshCommandRunner::class, fakeSshRunner(
        "203a-4316-6c90 2313/-/-         GE0/0/1             dynamic\n".
        "5c26-0a12-3456 2313/-/-         GE0/0/3             dynamic\n"
    ));

    PollSwitchMacTable::dispatch($device);

    expect(NetworkMacHistory::count())->toBe(1);
    expect(NetworkMacHistory::first()->port)->toBe('GE0/0/3');
    expect($device->fresh()->last_poll_status)->toBe('ok');
});

test('trunk-port matching is case-insensitive, so a hand-typed devices.json trunk_ports entry still excludes it', function () {
    $device = NetworkDevice::factory()->create(['vendor' => 'huawei']);
    // Lowercase, as a manually-typed trunk_ports value might come in - the
    // live MAC table below reports it uppercase, as switches actually do.
    NetworkDevicePort::create(['network_device_id' => $device->id, 'port' => 'ge0/0/1', 'link_type' => 'trunk']);

    $this->app->instance(SshCommandRunner::class, fakeSshRunner(
        "203a-4316-6c90 2313/-/-         GE0/0/1             dynamic\n"
    ));

    PollSwitchMacTable::dispatch($device);

    expect(NetworkMacHistory::count())->toBe(0);
});

test('a device with protocol=telnet is polled via TelnetCommandRunner, not SshCommandRunner', function () {
    $this->app->instance(TelnetCommandRunner::class, fakeTelnetRunner(
        "  2327    0018.0a1b.2c3d    DYNAMIC     Fa0/1\n"
    ));
    $device = NetworkDevice::factory()->create(['vendor' => 'cisco', 'protocol' => 'telnet']);

    PollSwitchMacTable::dispatch($device);

    $row = NetworkMacHistory::first();
    expect($row->mac_address)->toBe('00:18:0a:1b:2c:3d');
    expect($row->port)->toBe('Fa0/1');
    expect($device->fresh()->last_poll_status)->toBe('ok');
});

test('a failed SSH connection marks the device errored with the real exception message, not a generic one', function () {
    $this->app->instance(SshCommandRunner::class, fakeFailingSshRunner('SSH authentication rejected for uopnoc@10.23.255.78'));
    $device = NetworkDevice::factory()->create(['vendor' => 'huawei']);

    PollSwitchMacTable::dispatch($device);

    expect(NetworkMacHistory::count())->toBe(0);
    expect($device->fresh()->last_poll_status)->toBe('error');
    expect($device->fresh()->last_poll_error)->toBe('SSH authentication rejected for uopnoc@10.23.255.78');
});
