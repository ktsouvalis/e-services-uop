<?php

namespace App\Services\Pangolin;

/**
 * Ported from logs_viewer.py's parse_access_sessions() — pairs Newt's
 * "ACCESS START"/"ACCESS END" log lines by session id into complete session
 * records. An unmatched START (still-open session) is included with a null
 * ended/duration.
 *
 * Each record is one raw TCP/UDP flow, not a logical user session: Newt
 * logs a START/END pair per connection, and its own consolidateSessions()
 * merging (same src IP + dst + proto + resource, <= 5s gap) only applies to
 * the batch it ships to Pangolin — never to these log lines. Merging into
 * logical sessions happens at read time in NewtSessionConsolidator.
 *
 * Failed dials: Newt logs ACCESS START *before* dialing the destination. If
 * the dial fails it logs
 *   `INFO: 2026/09/28 08:46:03 TCP Forwarder: Failed to connect to 10.16.2.10:5000: dial tcp ...: i/o timeout`
 * (timestamp in the Newt container's timezone, no session id) and then
 * immediately the ACCESS END for that session. So a failure is correlated
 * by destination in line order: each Failed line goes onto a per-destination
 * FIFO, and the next TCP ACCESS END to that destination consumes the oldest
 * pending one. With tcpConnectTimeout at 5s, a timed-out dial shows up as an
 * exactly-5s "session" and a refused one as ~0s — but duration alone is
 * never used to mark a session failed; only a Failed line does. Known
 * limitation: a successful flow to the same destination that happens to END
 * between a Failed line and its own END would take the failure instead.
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

    private const FAILED_RE = '/TCP Forwarder: Failed to connect to (?P<dst>[\d.]+:\d+): (?P<error>.*)$/';

    /** @return array<int, array{session: string, resource_id: int, proto: string, src_ip: string, src_port: string, dst_ip: string, dst_port: string, started: string, ended: ?string, duration: ?string, failed: bool, failure_reason: ?string, failure_detail: ?string}> */
    public function parse(string $rawLog): array
    {
        $starts = [];
        $sessions = [];
        /** @var array<string, array<int, string>> $pendingFailures "ip:port" => FIFO of raw error texts */
        $pendingFailures = [];

        foreach (explode("\n", $rawLog) as $line) {
            if (preg_match(self::START_RE, $line, $m)) {
                $starts[$m['session']] = $m;

                continue;
            }
            if (preg_match(self::FAILED_RE, rtrim($line), $m)) {
                $pendingFailures[$m['dst']][] = trim($m['error']);

                continue;
            }
            if (preg_match(self::END_RE, $line, $m)) {
                unset($starts[$m['session']]);

                // Only the TCP forwarder logs Failed lines.
                $dst = "{$m['dst']}:{$m['dport']}";
                $error = strtolower($m['proto']) === 'tcp' && ! empty($pendingFailures[$dst])
                    ? array_shift($pendingFailures[$dst])
                    : null;

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
                    ...$this->failureFields($error),
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
                ...$this->failureFields(null),
            ];
        }

        return $sessions;
    }

    /** refused | timeout | unreachable | other, from a dial error's text. */
    public static function classifyFailure(string $error): string
    {
        $error = strtolower($error);

        return match (true) {
            str_contains($error, 'refused') => 'refused',
            str_contains($error, 'i/o timeout') || str_contains($error, 'context deadline exceeded') => 'timeout',
            str_contains($error, 'no route to host') || str_contains($error, 'network is unreachable') => 'unreachable',
            default => 'other',
        };
    }

    /** @return array{failed: bool, failure_reason: ?string, failure_detail: ?string} */
    private function failureFields(?string $error): array
    {
        return [
            'failed' => $error !== null,
            'failure_reason' => $error !== null ? self::classifyFailure($error) : null,
            'failure_detail' => $error,
        ];
    }
}
