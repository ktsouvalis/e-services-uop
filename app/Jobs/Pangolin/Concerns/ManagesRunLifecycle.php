<?php

namespace App\Jobs\Pangolin\Concerns;

/**
 * Shared run-start/run-finish boilerplate for RunLogsFetch/RunImport/RunNormalize
 * — same per-run working directory setup and final status/finished_at update,
 * previously duplicated near-verbatim across all three.
 */
trait ManagesRunLifecycle
{
    /**
     * Create the per-run working directory (if missing) and mark the run as
     * started. 0777: written by both the queue-worker (root) and the web
     * process (www-data) — see ConfigYamlWriter for the full rationale.
     * is_dir() guard: a retried attempt hits an existing dir from the prior
     * attempt — plain mkdir() throws "File exists".
     */
    private function startRun(string $runDir): void
    {
        if (! is_dir($runDir)) {
            mkdir($runDir, 0777, true);
        }

        $this->run->update(['status' => 'running', 'started_at' => now()]);
    }

    private function finishRun(array $attributes): void
    {
        $this->run->update($attributes + ['finished_at' => now()]);
    }
}
