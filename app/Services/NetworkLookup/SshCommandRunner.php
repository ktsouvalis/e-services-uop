<?php

namespace App\Services\NetworkLookup;

use phpseclib3\Net\SSH2;
use RuntimeException;
use Throwable;

/**
 * Password-auth SSH command runner for the network switches/core, distinct
 * from Pangolin/Authentik's ClusterMonitor SSH code (key-based login only,
 * no exec) - see config/network-lookup.php for why these devices need
 * username+password instead.
 */
class SshCommandRunner implements DeviceCommandRunnerContract
{
    /**
     * Matches a fresh CLI prompt at the end of the buffer - Huawei VRP ends
     * user-view output in "<DeviceName>" (or "[DeviceName]" in system view),
     * Cisco IOS in "DeviceName>" or "DeviceName#". Passing this to read()
     * lets it return as soon as the device is done, instead of phpseclib3's
     * default behavior for an empty $expect: it never matches, so read()
     * blocks for the *entire* configured timeout on every single call
     * (confirmed live - each command took ~20s instead of under 1s).
     */
    private const PROMPT_PATTERN = '/[\r\n][^\r\n]*[>\]#]\s*\z/';

    public function run(string $host, array $commands): string
    {
        $username = config('network-lookup.ssh.username');
        $password = config('network-lookup.ssh.password');
        $connectTimeout = (int) config('network-lookup.ssh.connect_timeout', 10);
        $execTimeout = (int) config('network-lookup.ssh.exec_timeout', 20);

        try {
            $ssh = new SSH2($host, 22, $connectTimeout);
            $ssh->setTimeout($execTimeout);

            if (! $ssh->login($username, $password)) {
                throw new RuntimeException("SSH authentication rejected for {$username}@{$host}");
            }

            // Consume the post-login banner/first prompt before sending any
            // command, so each write()/read() pair below maps cleanly to one
            // command's own output.
            $ssh->read(self::PROMPT_PATTERN, SSH2::READ_REGEX);

            $output = '';
            foreach ($commands as $command) {
                $ssh->write($command."\n");
                $output .= $ssh->read(self::PROMPT_PATTERN, SSH2::READ_REGEX);
            }

            $ssh->disconnect();

            return $output;
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new RuntimeException("SSH error against {$host}: ".$e->getMessage(), previous: $e);
        }
    }
}
