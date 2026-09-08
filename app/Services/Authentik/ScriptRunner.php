<?php

namespace App\Services\Authentik;

use Illuminate\Support\Facades\Process;
use Illuminate\Contracts\Process\ProcessResult;

/**
 * Thin wrapper for shelling out to the akropolis-monitor binary (see
 * config/authentik.php) inside its own per-run working directory.
 *
 * Typed against the ProcessResult *contract*, not the concrete
 * Illuminate\Process\ProcessResult class — Process::fake() (used by this
 * module's tests) returns Illuminate\Process\FakeProcessResult, which
 * implements the same contract but doesn't extend the concrete class.
 */
class ScriptRunner
{
    public function run(string $subcommand, array $args, string $workingDirectory): ProcessResult
    {
        $bin = config('authentik.python.bin');

        return Process::path($workingDirectory)
            ->timeout(config('authentik.python.timeout', 600))
            ->run([$bin, $subcommand, ...$args]);
    }
}
