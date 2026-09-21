<?php

use App\Jobs\NetworkLookup\PollAllDevices;
use App\Models\NetworkDevice;
use App\Models\NetworkPollRun;
use App\Services\NetworkLookup\SshCommandRunner;

function fakePollAllRunner(): SshCommandRunner
{
    return new class extends SshCommandRunner {
        public function run(string $host, array $commands): string
        {
            return '';
        }
    };
}

test('dispatching creates one poll run and stamps every device with its id', function () {
    $this->app->instance(SshCommandRunner::class, fakePollAllRunner());

    $l2a = NetworkDevice::factory()->create(['vendor' => 'huawei']);
    $l2b = NetworkDevice::factory()->create(['vendor' => 'huawei']);
    $core = NetworkDevice::factory()->core()->create();
    $disabled = NetworkDevice::factory()->create(['enabled' => false]);

    PollAllDevices::dispatch();

    $run = NetworkPollRun::first();
    expect($run)->not->toBeNull();
    expect($run->device_count)->toBe(3); // 2 l2 + 1 core, not the disabled one

    expect($l2a->fresh()->last_poll_run_id)->toBe($run->id);
    expect($l2b->fresh()->last_poll_run_id)->toBe($run->id);
    expect($core->fresh()->last_poll_run_id)->toBe($run->id);
    expect($disabled->fresh()->last_poll_run_id)->toBeNull();
    expect($run->reportedCount())->toBe(3);
});

test('a second dispatch creates a distinct, higher-numbered run', function () {
    $this->app->instance(SshCommandRunner::class, fakePollAllRunner());
    $device = NetworkDevice::factory()->create(['vendor' => 'huawei']);

    PollAllDevices::dispatch();
    $firstRunId = $device->fresh()->last_poll_run_id;

    PollAllDevices::dispatch();
    $secondRunId = $device->fresh()->last_poll_run_id;

    expect($secondRunId)->toBeGreaterThan($firstRunId);
    expect(NetworkPollRun::count())->toBe(2);
});
