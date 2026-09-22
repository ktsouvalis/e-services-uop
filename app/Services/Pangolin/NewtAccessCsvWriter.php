<?php

namespace App\Services\Pangolin;

/**
 * Ported from logs_viewer.py's run_save_newt_access() CSV writer — same
 * columns, sorted newest-first per node, an SSH-error row when a node's
 * fetch failed.
 */
class NewtAccessCsvWriter
{
    /**
     * @param  array<string, array{ip: string}>  $newtNodes  node name -> info (only needs 'ip')
     * @param  array<string, array{raw: string, error: ?string}>  $rawLogsByNode
     * @param  array<int, string>  $siteMap
     * @param  array<string, string>  $clientMap
     * @param  array<int, string>  $targetMap
     */
    public function write(
        array $newtNodes,
        array $rawLogsByNode,
        NewtAccessLogParser $parser,
        NewtAccessLogResolver $resolver,
        array $siteMap,
        array $clientMap,
        array $targetMap,
        string $path,
    ): void {
        $fh = fopen($path, 'w');
        fputcsv($fh, ['Site', 'Site IP', 'Started', 'Ended', 'Duration', 'Who', 'Where', 'Proto', 'Destination']);

        foreach ($newtNodes as $nodeName => $info) {
            $entry = $rawLogsByNode[$nodeName] ?? ['raw' => '', 'error' => 'not fetched'];
            if ($entry['error']) {
                fputcsv($fh, [$nodeName, $info['ip'], '', '', '', "SSH error: {$entry['error']}", '', '', '']);

                continue;
            }

            $sessions = $parser->parse($entry['raw']);
            $rows = array_map(fn ($s) => $resolver->formatSession($s, $siteMap, $clientMap, $targetMap), $sessions);
            usort($rows, fn ($a, $b) => strcmp($b['started'], $a['started']));

            foreach ($rows as $r) {
                fputcsv($fh, [
                    $nodeName, $info['ip'],
                    $r['started'], $r['ended'], $r['duration'],
                    $r['who'], $r['where'], $r['proto'], $r['dst'],
                ]);
            }
        }

        fclose($fh);
    }
}
