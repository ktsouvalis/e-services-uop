<?php

namespace App\Services\NetworkLookup;

use RuntimeException;

/**
 * Telnet command runner for the Cisco devices in this network - confirmed
 * live that all 10 of them accept telnet only (no working SSH auth), even
 * though their config carries only a plain VTY password (`line vty ... /
 * password ... / login`, no `login local`), which over telnet means the
 * device prompts for "Password:" only, with no username exchange at all.
 * PHP has no built-in telnet client and phpseclib3 is SSH/SFTP/SCP only, so
 * this hand-rolls the minimal RFC 854 IAC option negotiation needed to get a
 * plain text session (refuses every option the server offers/requests -
 * sufficient for basic line-mode interaction, not a general-purpose client).
 */
class TelnetCommandRunner implements DeviceCommandRunnerContract
{
    private const PORT = 23;

    private const PROMPT_PATTERN = '/[\r\n][^\r\n]*[>\]#]\s*\z/';

    // Un-delimited pattern bodies, so they can be composed into a combined
    // regex (see the login-prompt read below) without ending up with stray
    // nested delimiters - each is wrapped in `/…/` only at the point of use.
    private const USERNAME_PROMPT_BODY = '[Uu]sername:\s*\z';

    private const PASSWORD_PROMPT_BODY = '[Pp]assword:\s*\z';

    private const IAC = "\xFF";

    private const WILL = "\xFB";

    private const WONT = "\xFC";

    private const DO = "\xFD";

    private const DONT = "\xFE";

    public function run(string $host, array $commands): string
    {
        $username = config('network-lookup.ssh.username');
        $password = config('network-lookup.ssh.password');
        $connectTimeout = (int) config('network-lookup.ssh.connect_timeout', 10);
        $execTimeout = (int) config('network-lookup.ssh.exec_timeout', 20);

        $socket = @fsockopen($host, self::PORT, $errno, $errstr, $connectTimeout);

        if ($socket === false) {
            throw new RuntimeException("Telnet connection to {$host} failed: {$errstr}");
        }

        stream_set_timeout($socket, $execTimeout);
        stream_set_blocking($socket, false);

        try {
            $login = $this->readUntil($socket, '/(?:'.self::USERNAME_PROMPT_BODY.'|'.self::PASSWORD_PROMPT_BODY.')/', $execTimeout, $host);

            if (preg_match('/'.self::USERNAME_PROMPT_BODY.'/', $login)) {
                $this->write($socket, $username);
                $login = $this->readUntil($socket, '/'.self::PASSWORD_PROMPT_BODY.'/', $execTimeout, $host);
            }

            $this->write($socket, $password);
            $banner = $this->readUntil($socket, self::PROMPT_PATTERN, $execTimeout, $host);

            if (preg_match('/'.self::PASSWORD_PROMPT_BODY.'/', $banner) || preg_match('/% *(bad|invalid|authentication fail)/i', $banner)) {
                throw new RuntimeException("Telnet authentication rejected for {$host}");
            }

            $output = '';
            foreach ($commands as $command) {
                $this->write($socket, $command);
                $output .= $this->readUntil($socket, self::PROMPT_PATTERN, $execTimeout, $host);
            }

            return $output;
        } finally {
            fclose($socket);
        }
    }

    private function write($socket, string $line): void
    {
        fwrite($socket, $line."\r\n");
    }

    private function readUntil($socket, string $pattern, int $timeout, string $host): string
    {
        $buffer = '';
        $deadline = microtime(true) + $timeout;

        while (microtime(true) < $deadline) {
            $chunk = fread($socket, 4096);

            if ($chunk === false || $chunk === '') {
                $meta = stream_get_meta_data($socket);
                if ($meta['eof']) {
                    throw new RuntimeException("Telnet connection to {$host} closed unexpectedly");
                }
                usleep(100000);

                continue;
            }

            $buffer .= $this->stripTelnetCommands($socket, $chunk);

            if (preg_match($pattern, $buffer)) {
                return $buffer;
            }
        }

        throw new RuntimeException("Telnet read timed out waiting for a response from {$host}");
    }

    /**
     * Strips RFC 854 IAC option-negotiation sequences out of the stream,
     * refusing every WILL/DO request (WONT/DONT) so the server falls back to
     * plain text instead of waiting on options this client doesn't support.
     */
    private function stripTelnetCommands($socket, string $chunk): string
    {
        $out = '';
        $len = strlen($chunk);

        for ($i = 0; $i < $len; $i++) {
            if ($chunk[$i] !== self::IAC) {
                $out .= $chunk[$i];

                continue;
            }

            $command = $chunk[$i + 1] ?? "\x00";

            if (in_array($command, [self::WILL, self::WONT, self::DO, self::DONT], true)) {
                $option = $chunk[$i + 2] ?? "\x00";
                $reply = match ($command) {
                    self::WILL => self::DONT,
                    self::DO => self::WONT,
                    default => null,
                };

                if ($reply !== null) {
                    fwrite($socket, self::IAC.$reply.$option);
                }

                $i += 2;

                continue;
            }

            // Other IAC sequences (e.g. an escaped 0xFF byte) - skip just the command byte.
            $i += 1;
        }

        return $out;
    }
}
