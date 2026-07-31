<?php

namespace App\Jobs\Pangolin;

use App\Jobs\Pangolin\Concerns\SummarizesXlsxReport;
use App\Models\PangolinRun;
use App\Services\Pangolin\ConfigYamlWriter;
use App\Services\Pangolin\PangolinApiClient;
use App\Services\Pangolin\ScriptRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use romanzipp\QueueMonitor\Traits\IsMonitored;

class RunNormalize implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, IsMonitored, Queueable, SerializesModels, SummarizesXlsxReport;

    public int $timeout = 900;

    public function __construct(private readonly PangolinRun $run)
    {
    }

    public function handle(ConfigYamlWriter $configWriter, ScriptRunner $runner, PangolinApiClient $apiClient): void
    {
        $runDir = storage_path("app/private/pangolin/runs/{$this->run->id}");
        // 0777: see RunImport for why (cross-uid access between queue-worker
        // (root) and the web process (www-data)). is_dir() guard: a retried
        // attempt hits an existing dir from the prior attempt — plain
        // mkdir() throws "File exists".
        if (! is_dir($runDir)) {
            mkdir($runDir, 0777, true);
        }

        $this->run->update(['status' => 'running', 'started_at' => now()]);

        $configWriter->write("{$runDir}/config.yml");

        $args = ['--config', 'config.yml', '--out', 'report.xlsx'];
        if ($this->run->options['apply'] ?? false) {
            // The Laravel UI's own confirm dialog is the human-confirmation step,
            // so --yes is always passed to keep the subprocess from blocking on stdin.
            $args[] = '--apply';
            $args[] = '--yes';
        }

        // Users only ever see niceIds (reports, the Pangolin dashboard) — normalize_private_resources.py's
        // --resource-id is numeric siteResourceId only, so resolve niceIds via the Integration API first.
        $resourceIds = $apiClient->resolveSiteResourceIds($this->run->options['resource_ids'] ?? []);
        foreach ($resourceIds as $id) {
            $args[] = '--resource-id';
            $args[] = (string) $id;
        }

        $result = $runner->run('normalize_private_resources.py', $args, $runDir);

        $reportPath = "{$runDir}/report.xlsx";
        $reportExists = file_exists($reportPath);

        try {
            $summary = $reportExists ? $this->summarizeStatusColumn($reportPath, 'Normalize Report', 15) : null;
        } catch (\Throwable $e) {
            report($e);
            $summary = null;
        }

        $this->run->update([
            'status' => $result->successful() && $reportExists ? 'completed' : 'failed',
            'report_path' => $reportExists ? $reportPath : null,
            'summary' => $summary,
            'stdout' => $result->output().$result->errorOutput(),
            'error' => $result->successful() ? null : "Exit code {$result->exitCode()}",
            'finished_at' => now(),
        ]);
    }
}
