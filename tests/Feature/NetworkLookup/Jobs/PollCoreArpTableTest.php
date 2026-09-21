<?php

use App\Jobs\NetworkLookup\PollCoreArpTable;
use App\Models\NetworkArpHistory;
use App\Models\NetworkDevice;
use App\Services\NetworkLookup\SshCommandRunner;

function fakeArpSshRunner(string $output): SshCommandRunner
{
    return new class($output) extends SshCommandRunner {
        public function __construct(private readonly string $output) {}

        public function run(string $host, array $commands): string
        {
            return $this->output;
        }
    };
}

test('a fresh poll creates one arp_history row per parsed entry', function () {
    $this->app->instance(SshCommandRunner::class, fakeArpSshRunner(
        "10.23.14.156    183d-2da2-fd87  20        D-0/2313       Vlanif2313\n"
    ));
    $device = NetworkDevice::factory()->core()->create();

    PollCoreArpTable::dispatch($device);

    $row = NetworkArpHistory::first();
    expect($row->ip_address)->toBe('10.23.14.156');
    expect($row->mac_address)->toBe('18:3d:2d:a2:fd:87');
    expect($row->vlan)->toBe('2313');
    expect($device->fresh()->last_poll_status)->toBe('ok');
});

test('polling twice with the same ip/mac touches last_seen_at instead of inserting a new row', function () {
    $this->app->instance(SshCommandRunner::class, fakeArpSshRunner(
        "10.23.14.156    183d-2da2-fd87  20        D-0/2313       Vlanif2313\n"
    ));
    $device = NetworkDevice::factory()->core()->create();

    PollCoreArpTable::dispatch($device);
    $firstSeenAt = NetworkArpHistory::first()->first_seen_at;

    $this->travel(5)->minutes();
    PollCoreArpTable::dispatch($device);

    expect(NetworkArpHistory::count())->toBe(1);
    $row = NetworkArpHistory::first();
    expect($row->first_seen_at->equalTo($firstSeenAt))->toBeTrue();
    expect($row->last_seen_at->isAfter($firstSeenAt))->toBeTrue();
});

test('an ip resolving to a different mac opens a new history row instead of overwriting the old one', function () {
    $this->app->instance(SshCommandRunner::class, fakeArpSshRunner(
        "10.23.14.156    183d-2da2-fd87  20        D-0/2313       Vlanif2313\n"
    ));
    $device = NetworkDevice::factory()->core()->create();
    PollCoreArpTable::dispatch($device);

    $this->app->instance(SshCommandRunner::class, fakeArpSshRunner(
        "10.23.14.156    203a-4316-6c90  20        D-0/2313       Vlanif2313\n"
    ));
    $this->travel(5)->minutes();
    PollCoreArpTable::dispatch($device);

    expect(NetworkArpHistory::count())->toBe(2);
    $macs = NetworkArpHistory::orderBy('id')->pluck('mac_address')->all();
    expect($macs)->toBe(['18:3d:2d:a2:fd:87', '20:3a:43:16:6c:90']);
});
