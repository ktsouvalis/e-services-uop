<?php

namespace App\Services\Pangolin;

use Carbon\Carbon;

/**
 * Ported from logs_viewer.py's run_save() text-report writer — same plain
 * text layout, one NODE section per node, one SERVICE subsection per
 * service, output from LogFetcher::fetchAll() looked up by LogFetcher::key().
 */
class LogsReportWriter
{
    /**
     * @param  array<string, array{ip: string, services: array<int, array{0: string, 1: string, 2: string}>}>  $nodeMap
     * @param  array<string, array{output: string, error: ?string}>  $results
     */
    public function write(array $nodeMap, array $results, int $hours, string $level, string $path): void
    {
        $lines = [];
        $lines[] = 'Pangolin HA Cluster — Log Report';
        $lines[] = 'Fetched:  '.Carbon::now()->format('Y-m-d H:i:s');
        $lines[] = "Scope:    last {$hours}h, {$level} and above";
        $lines[] = str_repeat('=', 80);

        foreach ($nodeMap as $nodeName => $info) {
            $lines[] = '';
            $lines[] = "NODE: {$nodeName}  ({$info['ip']})";
            $lines[] = str_repeat('=', 80);

            foreach ($info['services'] as [$label, $type, $identifier]) {
                $lines[] = '';
                $lines[] = "  SERVICE: {$label}  [{$type}: {$identifier}]";
                $lines[] = '  '.str_repeat('─', 60);

                $result = $results[LogFetcher::key($nodeName, $label)] ?? ['output' => '', 'error' => 'not fetched'];
                if ($result['error']) {
                    $lines[] = "  SSH error: {$result['error']}";
                } elseif ($result['output'] === '') {
                    $lines[] = "  (no warnings or errors in the last {$hours}h)";
                } else {
                    foreach (explode("\n", $result['output']) as $line) {
                        $lines[] = "  {$line}";
                    }
                }
            }
        }

        file_put_contents($path, implode("\n", $lines)."\n");
    }
}
