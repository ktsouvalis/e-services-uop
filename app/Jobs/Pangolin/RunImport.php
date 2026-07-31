<?php

namespace App\Jobs\Pangolin;

use App\Jobs\Pangolin\Concerns\SummarizesXlsxReport;
use App\Models\PangolinRun;
use App\Services\Pangolin\ConfigYamlWriter;
use App\Services\Pangolin\ScriptRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use romanzipp\QueueMonitor\Traits\IsMonitored;

class RunImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, IsMonitored, Queueable, SerializesModels, SummarizesXlsxReport;

    public int $timeout = 900;

    public function __construct(private readonly PangolinRun $run)
    {
    }

    public function handle(ConfigYamlWriter $configWriter, ScriptRunner $runner): void
    {
        $runDir = storage_path("app/private/pangolin/runs/{$this->run->id}");
        // 0777: written by queue-worker (root), read/written by the web
        // process (www-data) for sibling dirs like pangolin/imports/ and for
        // serving downloads — 0700/0755 breaks one side or the other.
        mkdir($runDir, 0777, true);

        $this->run->update(['status' => 'running', 'started_at' => now()]);

        $configWriter->write("{$runDir}/config.yml");
        copy($this->run->input_path, "{$runDir}/input.xlsx");

        $args = ['input.xlsx', '--config', 'config.yml'];
        if ($this->run->options['dry_run'] ?? false) {
            $args[] = '--dry-run';
        }

        $result = $runner->run('create_private_resources.py', $args, $runDir);

        $reportPath = collect(glob("{$runDir}/input_results_*.xlsx"))->first();

        try {
            $summary = $reportPath ? $this->summarizeStatusColumn($reportPath, 'Results', 10) : null;
        } catch (\Throwable $e) {
            report($e);
            $summary = null;
        }

        $this->run->update([
            'status' => $result->successful() && $reportPath ? 'completed' : 'failed',
            'report_path' => $reportPath,
            'summary' => $summary,
            'stdout' => $result->output().$result->errorOutput(),
            'error' => $result->successful() ? null : "Exit code {$result->exitCode()}",
            'finished_at' => now(),
        ]);
    }
}
