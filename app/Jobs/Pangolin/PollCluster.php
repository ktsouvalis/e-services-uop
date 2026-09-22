<?php

namespace App\Jobs\Pangolin;

use App\Models\PangolinMonitorStatus;
use App\Services\Pangolin\ClusterMonitor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PollCluster implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;

    public function handle(ClusterMonitor $monitor): void
    {
        $newtNamesSeen = [];

        foreach ($monitor->run() as $row) {
            // Keyed on ['service', 'node_name'], not ['service', 'node_ip'] —
            // the single-node pangolin/gerbil/api rows have a fixed node_name
            // regardless of what IP the admin has configured (avoiding the
            // duplicate-fork bug fixed for Authentik), while Newt's rows
            // share service='newt' and are told apart by their distinct
            // host names. See the widen_pangolin_monitor_statuses_unique_key
            // migration.
            PangolinMonitorStatus::updateOrCreate(
                ['service' => $row['service'], 'node_name' => $row['node_name']],
                $row,
            );
            if ($row['service'] === 'newt') {
                $newtNamesSeen[] = $row['node_name'];
            }
        }

        // Newt agents are admin-managed and can be removed (see
        // PangolinNewtAgent) — without this, a deleted agent's last-known
        // status row would linger forever, showing a phantom entry on the
        // Monitor tab. pangolin/gerbil/api never need this: they're always
        // exactly one row each, every poll.
        PangolinMonitorStatus::where('service', 'newt')->whereNotIn('node_name', $newtNamesSeen)->delete();
    }
}
