<?php

namespace App\Jobs\Pangolin;

use App\Jobs\Pangolin\Concerns\ManagesRunLifecycle;
use App\Models\PangolinRun;
use App\Services\Pangolin\ConfigYamlWriter;
use App\Services\Pangolin\ScriptRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use romanzipp\QueueMonitor\Traits\IsMonitored;

class RunLogsFetch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, IsMonitored, ManagesRunLifecycle, Queueable, SerializesModels;

    public int $timeout = 900;

    public function __construct(private readonly PangolinRun $run)
    {
    }

    public function handle(ConfigYamlWriter $configWriter, ScriptRunner $runner): void
    {
        $runDir = storage_path("app/private/pangolin/runs/{$this->run->id}");
        $this->startRun($runDir);

        $configWriter->write("{$runDir}/config.yml");

        $args = ['--config', 'config.yml', '--save', 'cluster_logs'];
        if ($hours = $this->run->options['lookback_hours'] ?? null) {
            $args[] = '--last';
            $args[] = (string) $hours;
        }
        if ($level = $this->run->options['level'] ?? null) {
            $args[] = '--level';
            $args[] = $level;
        }

        $result = $runner->run('logs_viewer.py', $args, $runDir);

        $logPath = "{$runDir}/cluster_logs.log";
        $newtCsvPath = "{$runDir}/cluster_logs_newt.csv";

        $this->finishRun([
            'status' => $result->successful() && file_exists($logPath) ? 'completed' : 'failed',
            'report_path' => file_exists($logPath) ? $logPath : null,
            'extra_path' => file_exists($newtCsvPath) ? $newtCsvPath : null,
            'stdout' => $result->output().$result->errorOutput(),
            'error' => $result->successful() ? null : "Exit code {$result->exitCode()}",
        ]);
    }
}
