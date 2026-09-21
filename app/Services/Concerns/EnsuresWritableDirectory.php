<?php

namespace App\Services\Concerns;

use ErrorException;

/**
 * Creates (or normalizes) a directory that must be writable by more than one
 * OS user — e.g. storage/app/private/{pangolin,authentik}/runs, written by
 * both the web process (www-data) and the root-running queue-worker/
 * scheduler. See those modules' CLAUDE.md notes for the cross-uid rationale.
 *
 * mkdir()'s $permissions argument alone isn't enough: like the underlying
 * POSIX syscall, it's masked by the current process's umask (Apache's
 * www-data worker inherits 0022 here), so `mkdir($dir, 0777, true)` actually
 * produces 0755 — fine until a *different* uid needs to write into it. This
 * was caught live: a stray 0755 directory (created outside normal request
 * traffic, during manual testing) blocked a real request with a fatal
 * "mkdir(): Permission denied" the moment it tried to create a sibling
 * underneath it.
 */
trait EnsuresWritableDirectory
{
    private function ensureWritableDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
            chmod($dir, 0777);

            return;
        }

        if (is_writable($dir)) {
            return;
        }

        // Pre-existing and not writable by us — most likely created by the
        // other uid before this fix existed. Best-effort only: chmod() on a
        // directory we don't own throws (Laravel promotes the underlying
        // E_WARNING to an ErrorException), and a permissions problem outside
        // this request's control can't be safely self-healed from here —
        // don't let it fail the request either way.
        try {
            chmod($dir, 0777);
        } catch (ErrorException) {
        }
    }
}
