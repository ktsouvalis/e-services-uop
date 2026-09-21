<?php

namespace App\Jobs\NetworkLookup;

use App\Models\NetworkDevice;
use App\Models\NetworkPollRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Fan-out dispatcher: what both the scheduler (routes/console.php) and the
 * "poll now" button call. Dispatches one PollSwitchMacTable per enabled 'l2'
 * device plus one PollCoreArpTable per enabled 'core' device, rather than
 * doing the polling itself - each of those jobs runs independently in the
 * queue worker with its own $timeout.
 *
 * Every device job dispatched here is tagged with one NetworkPollRun's id,
 * so it's possible to tell "did every device report back for run #N" from
 * "some of these last_poll_status values are actually from an older run"
 * (e.g. a stuck job, a worker restart mid-run) - see NetworkPollRun::
 * reportedCount() and the device table's poll-run column.
 */
class PollAllDevices implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $devices = NetworkDevice::where('enabled', true)
            ->whereIn('role', ['l2', 'core'])
            ->get();

        $run = NetworkPollRun::create([
            'device_count' => $devices->count(),
            'started_at' => now(),
        ]);

        foreach ($devices as $device) {
            if ($device->role === 'core') {
                PollCoreArpTable::dispatch($device, $run->id);
            } else {
                PollSwitchMacTable::dispatch($device, $run->id);
            }
        }
    }
}
