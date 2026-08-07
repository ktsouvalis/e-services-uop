<?php

namespace App\Services\Pangolin;

use Illuminate\Support\Facades\Process;
use Illuminate\Contracts\Process\ProcessResult;

/**
 * Thin wrapper for shelling out to a pangolin-utils script inside its own
 * per-run working directory.
 *
 * Typed against the ProcessResult *contract*, not the concrete
 * Illuminate\Process\ProcessResult class — Process::fake() (used by this
 * module's tests) returns Illuminate\Process\FakeProcessResult, which
 * implements the same contract but doesn't extend the concrete class.
 */
class ScriptRunner
{
    public function run(string $script, array $args, string $workingDirectory): ProcessResult
    {
        $bin = config('pangolin.python.bin');
        $scriptPath = rtrim(config('pangolin.python.scripts_path'), '/').'/'.$script;

        return Process::path($workingDirectory)
            ->timeout(config('pangolin.python.timeout', 600))
            ->run([$bin, $scriptPath, ...$args]);
    }
}
