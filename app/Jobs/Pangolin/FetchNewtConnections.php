<?php

namespace App\Jobs\Pangolin;

use App\Jobs\Pangolin\Concerns\ManagesRunLifecycle;
use App\Models\PangolinRun;
use App\Services\Pangolin\NewtConnectionSync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use romanzipp\QueueMonitor\Traits\IsMonitored;

/**
 * Dispatched by PangolinController::newtConnectionsFetch() — pulls and
 * resolves every Newt agent's ACCESS sessions (App\Services\Pangolin\
 * NewtConnectionSync) and upserts them into pangolin_newt_connections. No
 * report_path/download: this run's output is the connections table itself,
 * browsed live in the "Connections" tab. See CLAUDE.md's Pangolin module
 * section.
 */
class FetchNewtConnections implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, IsMonitored, ManagesRunLifecycle, Queueable, SerializesModels;

    public int $timeout = 900;

    public function __construct(private readonly PangolinRun $run)
    {
    }

    public function handle(NewtConnectionSync $sync): void
    {
        $this->startRun(storage_path("app/private/pangolin/runs/{$this->run->id}"));

        $result = $sync->run();

        $this->finishRun([
            'status' => 'completed',
            'summary' => $result,
            'error' => $result['agent_errors'] ? implode('; ', $result['agent_errors']) : null,
        ]);
    }
}
