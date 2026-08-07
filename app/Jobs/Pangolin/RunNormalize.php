<?php

namespace App\Jobs\Pangolin;

use App\Jobs\Pangolin\Concerns\ManagesRunLifecycle;
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
    use Dispatchable, InteractsWithQueue, IsMonitored, ManagesRunLifecycle, Queueable, SerializesModels, SummarizesXlsxReport;

    public int $timeout = 900;

    public function __construct(private readonly PangolinRun $run)
    {
    }

    public function handle(ConfigYamlWriter $configWriter, ScriptRunner $runner, PangolinApiClient $apiClient): void
    {
        $runDir = storage_path("app/private/pangolin/runs/{$this->run->id}");
        $this->startRun($runDir);

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
        $requestedIds = $this->run->options['resource_ids'] ?? [];
        $resourceIds = $apiClient->resolveSiteResourceIds($requestedIds);

        if ($requestedIds && ! $resourceIds) {
            // resolveSiteResourceIds() silently drops any token that doesn't
            // resolve to a live resource. If every requested niceId/id was
            // mistyped, passing zero --resource-id args would make the script
            // apply to *every* private resource in the org instead of the
            // scope the user actually asked for — refuse rather than run
            // unscoped, especially since --apply --yes has no other prompt.
            $this->finishRun([
                'status' => 'failed',
                'error' => 'None of the requested resource IDs/niceIds resolved to a live Pangolin resource — refusing to run unscoped.',
            ]);

            return;
        }

        foreach ($resourceIds as $id) {
            $args[] = '--resource-id';
            $args[] = (string) $id;
        }

        $result = $runner->run('normalize_private_resources.py', $args, $runDir);

        $reportPath = "{$runDir}/report.xlsx";
        $reportExists = file_exists($reportPath);

        try {
            $summary = $reportExists ? $this->summarizeStatusColumn($reportPath, 'Normalize Report', 'Status') : null;
        } catch (\Throwable $e) {
            report($e);
            $summary = null;
        }

        $this->finishRun([
            'status' => $result->successful() && $reportExists ? 'completed' : 'failed',
            'report_path' => $reportExists ? $reportPath : null,
            'summary' => $summary,
            'stdout' => $result->output().$result->errorOutput(),
            'error' => $result->successful() ? null : "Exit code {$result->exitCode()}",
        ]);
    }
}
