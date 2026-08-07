<?php

namespace App\Jobs\Pangolin;

use App\Jobs\Pangolin\Concerns\ManagesRunLifecycle;
use App\Jobs\Pangolin\Concerns\SummarizesXlsxReport;
use App\Models\PangolinRun;
use App\Services\Pangolin\ConfigYamlWriter;
use App\Services\Pangolin\ScriptRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use romanzipp\QueueMonitor\Traits\IsMonitored;

class RunImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, IsMonitored, ManagesRunLifecycle, Queueable, SerializesModels, SummarizesXlsxReport;

    public int $timeout = 900;

    public function __construct(private readonly PangolinRun $run)
    {
    }

    public function handle(ConfigYamlWriter $configWriter, ScriptRunner $runner): void
    {
        $runDir = storage_path("app/private/pangolin/runs/{$this->run->id}");
        $this->startRun($runDir);

        $configWriter->write("{$runDir}/config.yml");
        copy($this->run->input_path, "{$runDir}/input.xlsx");
        // The run directory now holds its own copy (input.xlsx) — the
        // originally uploaded file under pangolin/imports/ is never read or
        // cleaned up again after this, so every import used to permanently
        // orphan a duplicate on disk.
        File::delete($this->run->input_path);

        $args = ['input.xlsx', '--config', 'config.yml'];
        if ($this->run->options['dry_run'] ?? false) {
            $args[] = '--dry-run';
        }

        $result = $runner->run('create_private_resources.py', $args, $runDir);

        $reportPath = collect(glob("{$runDir}/input_results_*.xlsx"))->first();

        try {
            $summary = $reportPath ? $this->summarizeStatusColumn($reportPath, 'Results', 'Status') : null;
        } catch (\Throwable $e) {
            report($e);
            $summary = null;
        }

        $this->finishRun([
            'status' => $result->successful() && $reportPath ? 'completed' : 'failed',
            'report_path' => $reportPath,
            'summary' => $summary,
            'stdout' => $result->output().$result->errorOutput(),
            'error' => $result->successful() ? null : "Exit code {$result->exitCode()}",
        ]);
    }
}
