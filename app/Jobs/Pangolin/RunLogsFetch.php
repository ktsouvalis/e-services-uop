<?php

namespace App\Jobs\Pangolin;

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
    use Dispatchable, InteractsWithQueue, IsMonitored, Queueable, SerializesModels;

    public int $timeout = 900;

    public function __construct(private readonly PangolinRun $run)
    {
    }

    public function handle(ConfigYamlWriter $configWriter, ScriptRunner $runner): void
    {
        $runDir = storage_path("app/private/pangolin/runs/{$this->run->id}");
        // 0777: see RunImport for why (cross-uid access between queue-worker
        // (root) and the web process (www-data)). is_dir() guard: a retried
        // attempt (e.g. after the job failed downstream) hits an existing
        // dir from the prior attempt — plain mkdir() throws "File exists".
        if (! is_dir($runDir)) {
            mkdir($runDir, 0777, true);
        }

        $this->run->update(['status' => 'running', 'started_at' => now()]);

        $configWriter->write("{$runDir}/config.yml");

        $args = ['--config', 'config.yml', '--save', 'cluster_logs'];
        if ($hours = $this->run->options['lookback_hours'] ?? null) {
            $args[] = '--last';
            $args[] = (string) $hours;
        }

        $result = $runner->run('logs_viewer.py', $args, $runDir);

        $logPath = "{$runDir}/cluster_logs.log";
        $newtCsvPath = "{$runDir}/cluster_logs_newt.csv";

        $this->run->update([
            'status' => $result->successful() && file_exists($logPath) ? 'completed' : 'failed',
            'report_path' => file_exists($logPath) ? $logPath : null,
            'extra_path' => file_exists($newtCsvPath) ? $newtCsvPath : null,
            'stdout' => $result->output().$result->errorOutput(),
            'error' => $result->successful() ? null : "Exit code {$result->exitCode()}",
            'finished_at' => now(),
        ]);
    }
}
