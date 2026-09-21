<?php

namespace App\Jobs\Authentik;

use App\Models\AuthentikMonitorStatus;
use App\Services\Authentik\ClusterMonitor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Scheduled every minute (routes/console.php) alongside Pangolin's own poll.
 * Deliberately not IsMonitored (see the Pangolin module's CLAUDE.md note) —
 * it would flood /jobs with a new row every 60s forever for no operational
 * benefit.
 */
class PollCluster implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;

    public function handle(ClusterMonitor $monitor): void
    {
        foreach ($monitor->run() as $row) {
            AuthentikMonitorStatus::updateOrCreate(
                ['service' => $row['service'], 'node_ip' => $row['node_ip']],
                $row,
            );
        }
    }
}
