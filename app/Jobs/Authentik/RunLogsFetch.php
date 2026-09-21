<?php

namespace App\Jobs\Authentik;

use App\Models\AuthentikLogRun;
use App\Services\Authentik\ScriptRunner;
use App\Services\Concerns\EnsuresWritableDirectory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use romanzipp\QueueMonitor\Traits\IsMonitored;

class RunLogsFetch implements ShouldQueue
{
    use Dispatchable, EnsuresWritableDirectory, InteractsWithQueue, IsMonitored, Queueable, SerializesModels;

    public int $timeout = 900;

    public function __construct(private readonly AuthentikLogRun $run)
    {
    }

    public function handle(ScriptRunner $runner): void
    {
        // config.yml is normally already staged into this run's directory by
        // AuthentikController::logsFetch() before dispatch (the uploaded
        // file the user submitted with the fetch request) — nothing to
        // render here anymore now that config comes from the user, not
        // env/config (see CLAUDE.md's Authentik module section). The is_dir
        // guard is only a safety net for a job dispatched without going
        // through that controller action (e.g. a direct retry).
        $runDir = storage_path("app/private/authentik/runs/{$this->run->id}");
        $this->ensureWritableDirectory(dirname($runDir));
        $this->ensureWritableDirectory($runDir);

        $this->run->update(['status' => 'running', 'started_at' => now()]);

        $args = ['config.yml', '--save', 'cluster_logs'];
        if ($hours = $this->run->options['lookback_hours'] ?? null) {
            $args[] = '--last';
            $args[] = (string) $hours;
        }
        if ($level = $this->run->options['level'] ?? null) {
            $args[] = '--level';
            $args[] = $level;
        }

        $result = $runner->run('logs', $args, $runDir);

        $logPath = "{$runDir}/cluster_logs.log";

        $this->run->update([
            'status' => $result->successful() && file_exists($logPath) ? 'completed' : 'failed',
            'report_path' => file_exists($logPath) ? $logPath : null,
            'stdout' => $result->output().$result->errorOutput(),
            'error' => $result->successful() ? null : "Exit code {$result->exitCode()}",
            'finished_at' => now(),
        ]);
    }
}
