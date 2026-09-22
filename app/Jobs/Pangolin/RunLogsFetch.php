<?php

namespace App\Jobs\Pangolin;

use App\Jobs\Pangolin\Concerns\ManagesRunLifecycle;
use App\Models\PangolinRun;
use App\Services\Pangolin\LogFetcher;
use App\Services\Pangolin\LogsNodeMap;
use App\Services\Pangolin\LogsReportWriter;
use App\Services\Pangolin\NewtAccessCsvWriter;
use App\Services\Pangolin\NewtAccessLogParser;
use App\Services\Pangolin\NewtAccessLogResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use romanzipp\QueueMonitor\Traits\IsMonitored;
use Throwable;

/**
 * Native PHP port of logs_viewer.py's --save mode (2026-09-22) — the last of
 * the three pangolin-utils scripts to be ported, completing the removal of
 * the submodule dependency entirely. See CLAUDE.md's Pangolin module
 * section: fetches WARN/ERROR-and-above logs over SSH from every configured
 * node/service (LogsNodeMap/LogFetcher), plus resolved Newt ACCESS session
 * logs via a direct Postgres connection (NewtAccessLogParser/Resolver),
 * matching the Python original's two output files exactly.
 */
class RunLogsFetch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, IsMonitored, ManagesRunLifecycle, Queueable, SerializesModels;

    public int $timeout = 900;

    public function __construct(private readonly PangolinRun $run)
    {
    }

    public function handle(
        LogsNodeMap $nodeMapBuilder,
        LogFetcher $fetcher,
        NewtAccessLogParser $accessParser,
        NewtAccessLogResolver $resolver,
        LogsReportWriter $reportWriter,
        NewtAccessCsvWriter $csvWriter,
    ): void {
        $runDir = storage_path("app/private/pangolin/runs/{$this->run->id}");
        $this->startRun($runDir);

        // logs_viewer.py's --last overrides BOTH the service-log window (24h
        // default) and the separate, longer Newt access-log window (7 days
        // default) to the same value when given — only the two defaults
        // actually differ.
        $lookback = $this->run->options['lookback_hours'] ?? null;
        $hours = $lookback ?: 24;
        $accessHours = $lookback ?: 168;
        $level = $this->run->options['level'] ?? 'warning';

        $nodeMap = $nodeMapBuilder->build();
        if (! $nodeMap) {
            $this->finishRun([
                'status' => 'failed',
                'error' => 'No services/nodes configured for log fetching — check config(\'pangolin.services\'/\'pangolin.nodes\').',
            ]);

            return;
        }

        $results = $fetcher->fetchAll($nodeMap, $hours, $level);

        $logPath = "{$runDir}/cluster_logs.log";
        $reportWriter->write($nodeMap, $results, $hours, $level, $logPath);

        // node_has_newt(): a node is in scope for the separate Newt access
        // CSV when its services list includes a "Newt"-labeled entry —
        // every node in the DB-managed pangolin_newt_agents table naturally
        // qualifies, since that's the only group the "Newt" service maps to.
        $newtNodeMap = array_filter($nodeMap, fn ($info) => collect($info['services'])->contains(fn ($s) => $s[0] === 'Newt'));

        $newtCsvPath = null;
        if ($newtNodeMap) {
            $rawLogs = $fetcher->fetchNewtFullLogs($newtNodeMap, $accessHours);

            try {
                $pdo = $resolver->connect();
                [$siteMap, $clientMap, $targetMap] = $resolver->buildLookupMaps($pdo);
            } catch (Throwable $e) {
                // Falls back to raw IPs/IDs in the CSV rather than failing
                // the whole run — matches logs_viewer.py's own documented
                // fallback when Postgres isn't reachable from wherever the
                // job runs. See CLAUDE.md's Pangolin module section.
                report($e);
                $siteMap = $clientMap = $targetMap = [];
            }

            $newtCsvPath = "{$runDir}/cluster_logs_newt.csv";
            $csvWriter->write($newtNodeMap, $rawLogs, $accessParser, $resolver, $siteMap, $clientMap, $targetMap, $newtCsvPath);
        }

        $this->finishRun([
            'status' => 'completed',
            'report_path' => $logPath,
            'extra_path' => $newtCsvPath,
            'error' => null,
        ]);
    }
}
