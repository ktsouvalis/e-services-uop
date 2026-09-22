<?php

namespace App\Services\Pangolin;

use Throwable;

/**
 * Ported from logs_viewer.py's docker_log_cmd()/systemd_log_cmd()/run_save()
 * (the --save path only — the TUI mode was never used by this app). Fetches
 * WARN/ERROR-and-above logs for every (node, service) pair in a node map
 * built by LogsNodeMap.
 *
 * Sequential, not the Python original's ThreadPoolExecutor(16) — phpseclib3
 * has no async story, and introducing real concurrency (pcntl/Fibers) for
 * what's typically ~2-3 nodes x a handful of services is more risk than
 * it's worth given RunLogsFetch's 900s job timeout comfortably covers a
 * sequential run. If this ever grows to enough nodes/services that
 * sequential fetching becomes a real problem, that's the thing to revisit.
 */
class LogFetcher
{
    private const MAX_LINES = 500;

    private const LOG_LEVELS = [
        'debug' => ['grep' => null, 'journalctl' => 'debug'],
        'info' => ['grep' => '(INFO|WARN|WARNING|ERROR|CRITICAL|FATAL|CRIT)', 'journalctl' => 'info'],
        'warning' => ['grep' => '(WARN|WARNING|ERROR|CRITICAL|FATAL|CRIT)', 'journalctl' => 'warning'],
        'error' => ['grep' => '(ERROR|CRITICAL|FATAL|CRIT)', 'journalctl' => 'err'],
    ];

    public function __construct(private readonly SshCommandRunner $ssh)
    {
    }

    /**
     * @param  array<string, array{ip: string, ssh: array{0: string, 1: string}, services: array<int, array{0: string, 1: string, 2: string}>}>  $nodeMap
     * @return array<string, array{output: string, error: ?string}> keyed by "{nodeName}\0{label}"
     */
    public function fetchAll(array $nodeMap, int $hours, string $level): array
    {
        $results = [];
        foreach ($nodeMap as $nodeName => $info) {
            [$username, $keyPath] = $info['ssh'];
            foreach ($info['services'] as [$label, $type, $identifier]) {
                $cmd = $type === 'docker'
                    ? $this->dockerLogCmd($identifier, $hours, $level)
                    : $this->systemdLogCmd($identifier, $hours, $level);

                try {
                    $output = $this->ssh->run($info['ip'], $username, $keyPath, $cmd);
                    $results[self::key($nodeName, $label)] = ['output' => $output, 'error' => null];
                } catch (Throwable $e) {
                    $results[self::key($nodeName, $label)] = ['output' => '', 'error' => $e->getMessage()];
                }
            }
        }

        return $results;
    }

    public static function key(string $nodeName, string $label): string
    {
        return "{$nodeName}\0{$label}";
    }

    /**
     * Full (ungrepped) Newt log, needed to catch ACCESS START/END lines,
     * which are INFO level and would be filtered out by dockerLogCmd()'s
     * warning+ grep. Separate, longer lookback window than the main service
     * logs (see RunLogsFetch — 7 days by default vs. 24h).
     *
     * @param  array<string, array{ip: string, ssh: array{0: string, 1: string}}>  $newtNodeMap
     * @return array<string, array{raw: string, error: ?string}>
     */
    public function fetchNewtFullLogs(array $newtNodeMap, int $hours): array
    {
        $results = [];
        foreach ($newtNodeMap as $nodeName => $info) {
            [$username, $keyPath] = $info['ssh'];
            try {
                $raw = $this->ssh->run($info['ip'], $username, $keyPath, "docker logs --since {$hours}h newt 2>&1");
                $results[$nodeName] = ['raw' => $raw, 'error' => null];
            } catch (Throwable $e) {
                $results[$nodeName] = ['raw' => '', 'error' => $e->getMessage()];
            }
        }

        return $results;
    }

    private function dockerLogCmd(string $container, int $hours, string $level): string
    {
        $cmd = "docker logs --since {$hours}h {$container} 2>&1";
        $pattern = self::LOG_LEVELS[$level]['grep'] ?? null;
        if ($pattern) {
            $cmd .= " | grep -iE '{$pattern}'";
        }

        return $cmd." | tail -".self::MAX_LINES;
    }

    private function systemdLogCmd(string $unit, int $hours, string $level): string
    {
        $priority = self::LOG_LEVELS[$level]['journalctl'];

        return "journalctl -u {$unit} --since '{$hours} hours ago' --no-pager -p {$priority} -o short-iso | tail -".self::MAX_LINES;
    }
}
