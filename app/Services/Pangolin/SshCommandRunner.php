<?php

namespace App\Services\Pangolin;

use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;

/**
 * Thin wrapper around phpseclib3's SSH2, ported from logs_viewer.py's
 * ssh_run() — a single non-interactive command execution per call. Its own
 * class (rather than inlined) specifically so NewtConnectionSync's
 * orchestration logic can be tested without a real SSH server, by swapping
 * this binding in tests.
 */
class SshCommandRunner
{
    public function run(string $ip, string $username, string $keyPath, string $command, int $timeout = 30): string
    {
        $ssh = new SSH2($ip, 22, $timeout);
        $key = PublicKeyLoader::load(file_get_contents($keyPath));
        if (! $ssh->login($username, $key)) {
            throw new \RuntimeException('SSH authentication failed');
        }
        $output = $ssh->exec($command);
        $ssh->disconnect();

        return $output;
    }
}
