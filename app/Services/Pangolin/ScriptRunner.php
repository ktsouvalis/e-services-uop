<?php

namespace App\Services\Pangolin;

use Illuminate\Support\Facades\Process;
use Illuminate\Process\ProcessResult;

/**
 * Thin wrapper for shelling out to a pangolin-utils script inside its own
 * per-run working directory.
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
