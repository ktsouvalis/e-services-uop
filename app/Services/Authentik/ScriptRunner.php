<?php

namespace App\Services\Authentik;

use Illuminate\Support\Facades\Process;
use Illuminate\Process\ProcessResult;

/**
 * Thin wrapper for shelling out to an authentik-utils script inside its own
 * per-run working directory.
 */
class ScriptRunner
{
    public function run(string $script, array $args, string $workingDirectory): ProcessResult
    {
        $bin = config('authentik.python.bin');
        $scriptPath = rtrim(config('authentik.python.scripts_path'), '/').'/'.$script;

        return Process::path($workingDirectory)
            ->timeout(config('authentik.python.timeout', 600))
            ->run([$bin, $scriptPath, ...$args]);
    }
}
