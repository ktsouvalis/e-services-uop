<?php

namespace App\Services\Pangolin;

/**
 * Ported from logs_viewer.py's parse_access_sessions() — pairs Newt's
 * "ACCESS START"/"ACCESS END" log lines by session id into complete session
 * records. An unmatched START (still-open session) is included with a null
 * ended/duration.
 */
class NewtAccessLogParser
{
    private const START_RE = '/ACCESS START session=(?P<session>\S+) resource=(?P<resource>\d+) '.
        'proto=(?P<proto>\S+) src=(?P<src>[\d.]+):(?P<sport>\d+) '.
        'dst=(?P<dst>[\d.]+):(?P<dport>\d+) time=(?P<time>\S+)/';

    private const END_RE = '/ACCESS END session=(?P<session>\S+) resource=(?P<resource>\d+) '.
        'proto=(?P<proto>\S+) src=(?P<src>[\d.]+):(?P<sport>\d+) '.
        'dst=(?P<dst>[\d.]+):(?P<dport>\d+) started=(?P<started>\S+) '.
        'ended=(?P<ended>\S+) duration=(?P<duration>\S+)/';

    /** @return array<int, array{session: string, resource_id: int, proto: string, src_ip: string, src_port: string, dst_ip: string, dst_port: string, started: string, ended: ?string, duration: ?string}> */
    public function parse(string $rawLog): array
    {
        $starts = [];
        $sessions = [];

        foreach (explode("\n", $rawLog) as $line) {
            if (preg_match(self::START_RE, $line, $m)) {
                $starts[$m['session']] = $m;

                continue;
            }
            if (preg_match(self::END_RE, $line, $m)) {
                unset($starts[$m['session']]);
                $sessions[] = [
                    'session' => $m['session'],
                    'resource_id' => (int) $m['resource'],
                    'proto' => $m['proto'],
                    'src_ip' => $m['src'],
                    'src_port' => $m['sport'],
                    'dst_ip' => $m['dst'],
                    'dst_port' => $m['dport'],
                    'started' => $m['started'],
                    'ended' => $m['ended'],
                    'duration' => $m['duration'],
                ];
            }
        }

        // Any START without a matching END = still-open session.
        foreach ($starts as $sessionId => $s) {
            $sessions[] = [
                'session' => $sessionId,
                'resource_id' => (int) $s['resource'],
                'proto' => $s['proto'],
                'src_ip' => $s['src'],
                'src_port' => $s['sport'],
                'dst_ip' => $s['dst'],
                'dst_port' => $s['dport'],
                'started' => $s['time'],
                'ended' => null,
                'duration' => null,
            ];
        }

        return $sessions;
    }
}
