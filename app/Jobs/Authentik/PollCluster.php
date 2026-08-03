<?php

namespace App\Jobs\Authentik;

use App\Models\AuthentikMonitorStatus;
use App\Services\Authentik\ClusterMonitor;
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
        foreach ($monitor->run() as $row) {
            AuthentikMonitorStatus::updateOrCreate(
                ['service' => $row['service'], 'node_ip' => $row['node_ip']],
                $row,
            );
        }
    }
}
