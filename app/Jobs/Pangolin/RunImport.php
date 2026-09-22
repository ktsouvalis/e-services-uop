<?php

namespace App\Jobs\Pangolin;

use App\Jobs\Pangolin\Concerns\ManagesRunLifecycle;
use App\Jobs\Pangolin\Concerns\SummarizesXlsxReport;
use App\Models\PangolinRun;
use App\Services\Pangolin\ImportRequestParser;
use App\Services\Pangolin\ImportReportWriter;
use App\Services\Pangolin\ImportResourceCreator;
use App\Services\Pangolin\PangolinApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use romanzipp\QueueMonitor\Traits\IsMonitored;

/**
 * Native PHP port of create_private_resources.py (2026-09-22) — no longer
 * shells out to the pangolin-utils submodule. See CLAUDE.md's Pangolin
 * module section: each User Emails entry on a "Requests" row becomes its own
 * Pangolin private (site) resource via the Integration API, spanning every
 * org site for HA. ImportRequestParser/ImportResourceCreator/
 * ImportReportWriter mirror the Python original's parse_row()/
 * create_site_resource()/write_report() respectively — see those classes'
 * own docblocks for the naming/niceId/enabled rules this replicates exactly.
 */
class RunImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, IsMonitored, ManagesRunLifecycle, Queueable, SerializesModels, SummarizesXlsxReport;

    public int $timeout = 900;

    public function __construct(private readonly PangolinRun $run)
    {
    }

    public function handle(
        PangolinApiClient $api,
        ImportRequestParser $parser,
        ImportResourceCreator $creator,
        ImportReportWriter $reportWriter,
    ): void {
        $runDir = storage_path("app/private/pangolin/runs/{$this->run->id}");
        $this->startRun($runDir);

        $inputPath = "{$runDir}/input.xlsx";
        copy($this->run->input_path, $inputPath);
        // The run directory now holds its own copy (input.xlsx) — the
        // originally uploaded file under pangolin/imports/ is never read or
        // cleaned up again after this, so every import used to permanently
        // orphan a duplicate on disk.
        File::delete($this->run->input_path);

        $dryRun = (bool) ($this->run->options['dry_run'] ?? false);

        try {
            $api->verifyOrg();
            $sites = $api->listSites();
            if (! $sites) {
                throw new \RuntimeException('no sites found in this org');
            }
            $userIndex = $api->buildUserIndex();
        } catch (RequestException|\RuntimeException $e) {
            $this->finishRun([
                'status' => 'failed',
                'error' => 'Could not reach the Pangolin Integration API: '.$e->getMessage(),
            ]);

            return;
        }

        $workbook = IOFactory::load($inputPath);
        $sheet = $workbook->getSheetByName('Requests');
        if (! $sheet) {
            // Matches the Python original's hard failure on a missing sheet
            // (wb[args.sheet] raising KeyError) — but reported cleanly rather
            // than leaving the run stuck at "running" with nothing recorded,
            // same reasoning as the xlsx-report try/catch below.
            $this->finishRun([
                'status' => 'failed',
                'error' => "The uploaded file has no 'Requests' sheet.",
            ]);

            return;
        }
        $highestRow = $sheet->getHighestRow();

        $resolvedReqs = [];
        $upfrontFails = [];
        for ($rowNum = 2; $rowNum <= $highestRow; $rowNum++) {
            $row = [];
            for ($col = 1; $col <= 6; $col++) {
                $letter = Coordinate::stringFromColumnIndex($col);
                $row[] = $sheet->getCell("{$letter}{$rowNum}")->getCalculatedValue();
            }
            [$resolved, $fails] = $parser->parseRow($rowNum, $row, $userIndex);
            $resolvedReqs = array_merge($resolvedReqs, $resolved);
            $upfrontFails = array_merge($upfrontFails, $fails);
        }

        $allResults = [];
        foreach ($resolvedReqs as $req) {
            $allResults[] = $creator->create($sites, $req, $dryRun);
        }
        $allResults = array_merge($allResults, $upfrontFails);
        usort($allResults, fn ($a, $b) => $a['row_num'] <=> $b['row_num']);

        $reportPath = $reportWriter->write($workbook, $inputPath, $allResults);

        try {
            $summary = $this->summarizeStatusColumn($reportPath, 'Results', 'Status');
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
