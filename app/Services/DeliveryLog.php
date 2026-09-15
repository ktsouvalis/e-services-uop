<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Per-send delivery log files for Mailers/Sheetmailers - one file per send
 * (a Bus::batch() or a single dispatch), appended to line-by-line by the
 * send job(s) as each mail goes out. Separate from the daily
 * Log::channel('mailers'|'sheetmailers') files so one run's outcome doesn't
 * need to be grepped out of a whole day's unrelated activity, and
 * downloadable from that mailer's/sheetmailer's own Edit page. Shared
 * between both modules since the naming/storage/locking scheme is
 * identical - only the "application" tag (mailer/sheetmailer) differs.
 */
final class DeliveryLog
{
    /**
     * Reserve a fresh log file path for one send and ensure its directory
     * exists. Called once per send by the controller, before building the
     * job(s) that will append to it, so every job in a batch shares the
     * same file.
     */
    public static function newPath(string $application, int $id): string
    {
        $dir = self::directory($application, $id);

        if (! is_dir($dir)) {
            // 0777: written by the queue-worker container (runs as root) and
            // read back for download by the web container (runs as
            // www-data) - same cross-uid rationale as the Pangolin/Authentik
            // storage trees, see ConfigYamlWriter. mkdir()'s mode is masked
            // by the process umask (0022 here), so an explicit chmod is
            // needed too - mkdir(..., 0777) alone silently only produces
            // 0755.
            mkdir($dir, 0777, true);
            chmod($dir, 0777);
        }

        // A random suffix (not just the second-granularity timestamp) keeps two
        // sends started in the same second - e.g. clicking "Send" for several
        // departments in quick succession - from colliding on one filename and
        // interleaving into a single log.
        $filename = sprintf('mail_delivery_%s_%d_%s_%s.log', $application, $id, now()->format('Ymd_His'), Str::random(6));

        return $dir.'/'.$filename;
    }

    /**
     * Append one timestamped line to a delivery log. Locked because a
     * batch's jobs can be processed concurrently by multiple queue workers.
     */
    public static function append(string $path, string $line): void
    {
        $handle = fopen($path, 'a');

        if ($handle === false) {
            return;
        }

        flock($handle, LOCK_EX);
        fwrite($handle, '['.now()->toDateTimeString().'] '.$line.PHP_EOL);
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    /**
     * Past delivery log files for one mailer/sheetmailer, newest first, for
     * listing on its Edit page.
     */
    public static function listFor(string $application, int $id): array
    {
        $dir = self::directory($application, $id);

        if (! is_dir($dir)) {
            return [];
        }

        $files = glob($dir.'/*.log') ?: [];
        rsort($files);

        return array_map(fn (string $path) => [
            'filename' => basename($path),
            'size' => filesize($path),
            'modified_at' => Carbon::createFromTimestamp(filemtime($path)),
        ], $files);
    }

    /**
     * Resolve a filename (as shown by listFor()) to a full path for
     * download, refusing anything that isn't an exact, existing file inside
     * this mailer's/sheetmailer's own log directory - guards against path
     * traversal via a crafted filename in the download route.
     */
    public static function resolveForDownload(string $application, int $id, string $filename): ?string
    {
        if ($filename === '' || $filename !== basename($filename)) {
            return null;
        }

        $path = self::directory($application, $id).'/'.$filename;

        return is_file($path) ? $path : null;
    }

    private static function directory(string $application, int $id): string
    {
        return storage_path("app/private/{$application}s/logs/{$id}");
    }
}
