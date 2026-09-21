<?php

namespace App\Services\Authentik;

use App\Services\Concerns\EnsuresWritableDirectory;

/**
 * Remembers the last config_yml text pasted into the Logs tab, so
 * re-fetching doesn't mean re-pasting the same config.<site>.monitor.yml
 * every time — overwritten whenever a *different* value is submitted. (The
 * Monitor tab no longer pastes a config at all — it polls natively using DB-
 * persisted settings, see App\Models\AuthentikMonitorSettings — so this is
 * Logs-only now.) A single file on disk, not a DB row: a config someone
 * leans on daily shouldn't vanish because of an unrelated
 * `php artisan cache:clear`.
 */
class RememberedConfig
{
    use EnsuresWritableDirectory;

    public function get(): ?string
    {
        return file_exists($this->path()) ? file_get_contents($this->path()) : null;
    }

    public function remember(string $configYaml): void
    {
        $this->ensureWritableDirectory(dirname($this->path()));

        file_put_contents($this->path(), $configYaml);
        chmod($this->path(), 0600);
    }

    private function path(): string
    {
        return storage_path('app/private/authentik/last-config.yml');
    }
}
