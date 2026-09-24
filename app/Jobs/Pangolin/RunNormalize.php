<?php

namespace App\Jobs\Pangolin;

use App\Jobs\Pangolin\Concerns\ManagesRunLifecycle;
use App\Jobs\Pangolin\Concerns\SummarizesXlsxReport;
use App\Models\PangolinRun;
use App\Services\Pangolin\NormalizeProcessor;
use App\Services\Pangolin\NormalizeReportWriter;
use App\Services\Pangolin\PangolinApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use romanzipp\QueueMonitor\Traits\IsMonitored;

/**
 * Native PHP port of normalize_private_resources.py (2026-09-22) — no
 * longer shells out to the pangolin-utils submodule. See CLAUDE.md's
 * Pangolin module section and NormalizeProcessor's own docblock: this
 * audits/fixes real, live Pangolin private-resource naming, niceId, access
 * grants, and the `enabled`/`disableIcmp` switches. `apply` mirrors the
 * Python original's `--apply` flag; the Laravel UI's own confirm checkbox
 * is the human-confirmation step (there's no interactive prompt to bypass
 * here, since a queued job has no stdin either way).
 */
class RunNormalize implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, IsMonitored, ManagesRunLifecycle, Queueable, SerializesModels, SummarizesXlsxReport;

    public int $timeout = 900;

    // Creates/mutates live Pangolin resources and isn't idempotent — a retry
    // would re-apply a partially-completed run (and RunImport's uploaded
    // input file is already consumed by the first attempt).
    public int $tries = 1;

    public function __construct(private readonly PangolinRun $run)
    {
    }

    public function handle(PangolinApiClient $api, NormalizeProcessor $processor, NormalizeReportWriter $reportWriter): void
    {
        $runDir = storage_path("app/private/pangolin/runs/{$this->run->id}");
        $this->startRun($runDir);

        $applyChanges = (bool) ($this->run->options['apply'] ?? false);

        // Users only ever see niceIds (reports, the Pangolin dashboard) —
        // resolve them to numeric siteResourceIds via the Integration API first.
        $requestedIds = $this->run->options['resource_ids'] ?? [];

        try {
            $resourceIds = $api->resolveSiteResourceIds($requestedIds);
        } catch (RequestException $e) {
            $this->finishRun([
                'status' => 'failed',
                'error' => 'Could not reach the Pangolin Integration API: '.$e->getMessage(),
            ]);

            return;
        }

        if ($requestedIds && ! $resourceIds) {
            // resolveSiteResourceIds() silently drops any token that doesn't
            // resolve to a live resource. If every requested niceId/id was
            // mistyped, running with zero id filters would apply to *every*
            // private resource in the org instead of the scope the user
            // actually asked for — refuse rather than run unscoped.
            $this->finishRun([
                'status' => 'failed',
                'error' => 'None of the requested resource IDs/niceIds resolved to a live Pangolin resource — refusing to run unscoped.',
            ]);

            return;
        }

        try {
            $api->verifyOrg();
            $sites = $api->listSites();
            if (! $sites) {
                throw new \RuntimeException('no sites found in this org');
            }
            $resources = $api->listSiteResources();
            $orgEmailIndex = $api->buildOrgEmailIndex();
        } catch (RequestException|\RuntimeException $e) {
            $this->finishRun([
                'status' => 'failed',
                'error' => 'Could not reach the Pangolin Integration API: '.$e->getMessage(),
            ]);

            return;
        }

        if ($resourceIds) {
            $wanted = array_flip($resourceIds);
            $resources = array_values(array_filter($resources, fn ($r) => isset($wanted[$r['siteResourceId']])));
        }

        $rows = [];
        foreach ($resources as $res) {
            $rows[] = $processor->process($sites, $res, $orgEmailIndex, $applyChanges);
        }

        $reportPath = "{$runDir}/report.xlsx";
        $reportWriter->write($rows, $reportPath);

        try {
            $summary = $this->summarizeStatusColumn($reportPath, 'Normalize Report', 'Status');
        } catch (\Throwable $e) {
            report($e);
            $summary = null;
        }

        $this->finishRun([
            'status' => 'completed',
            'report_path' => $reportPath,
            'summary' => $summary,
            'error' => null,
        ]);
    }
}
