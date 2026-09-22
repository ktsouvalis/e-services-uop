<?php

namespace App\Services\Pangolin;

use PDO;

/**
 * Ported from logs_viewer.py's get_pg_connection()/build_lookup_maps()/
 * format_session() — resolves Newt ACCESS log sessions (raw IPs/site IDs)
 * into human-readable names via a direct Postgres connection to the
 * cluster's own database (config('pangolin.vip')/.ports.postgres/.postgres.*
 * — same connection details ConfigYamlWriter already rendered for the
 * Python original). Needs the pdo_pgsql extension (see both Dockerfiles).
 *
 * If the connection fails, callers are expected to catch and fall back to
 * raw IPs/IDs (formatSession() already does this gracefully when the maps
 * are simply empty) — matching logs_viewer.py's own documented fallback
 * behavior, not a bug to "fix" by failing the whole Logs run over a
 * Postgres reachability problem. See CLAUDE.md's Pangolin module section.
 */
class NewtAccessLogResolver
{
    public function connect(): PDO
    {
        $host = config('pangolin.vip');
        $port = config('pangolin.ports.postgres');
        $db = config('pangolin.postgres.database');
        $user = config('pangolin.postgres.user');
        $password = config('pangolin.postgres.password');

        return new PDO(
            "pgsql:host={$host};port={$port};dbname={$db};connect_timeout=10",
            $user,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }

    /**
     * Returns [siteMap (siteId -> name), clientMap (ip -> "who (client)"),
     * targetMap (siteResourceId -> name)].
     *
     * @return array{0: array<int, string>, 1: array<string, string>, 2: array<int, string>}
     */
    public function buildLookupMaps(PDO $pdo): array
    {
        $siteMap = [];
        foreach ($pdo->query('SELECT "siteId", name FROM sites')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $siteMap[(int) $row['siteId']] = $row['name'];
        }

        $clientMap = [];
        $stmt = $pdo->query('SELECT c.subnet, c.name, u.name AS user_name, u.email AS user_email '.
            'FROM clients c LEFT JOIN "user" u ON c."userId" = u.id');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            // subnet is CIDR notation (e.g. "10.1.2.3/32") — strip the prefix.
            $ip = strtok($row['subnet'], '/');
            $who = $row['user_name'] ?: ($row['user_email'] ?: 'unknown user');
            $clientMap[$ip] = "{$who} ({$row['name']})";
        }

        $targetMap = [];
        foreach ($pdo->query('SELECT "siteResourceId", name FROM "siteResources"')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $targetMap[(int) $row['siteResourceId']] = $row['name'];
        }

        return [$siteMap, $clientMap, $targetMap];
    }

    /**
     * Turn a parsed session (see NewtAccessLogParser) into a human-readable
     * row. Note: the "resource=" field in Newt's ACCESS log lines is
     * actually the Pangolin siteId, not a siteResourceId (confirmed live by
     * matching real siteIds against observed log values) — so the
     * $targetMap lookup below (keyed by siteResourceId) essentially never
     * hits in practice and this falls back to $siteMap. That's an
     * as-designed quirk inherited unchanged from the Python original, not
     * something introduced by this port — see its own module docstring.
     *
     * @param  array<int, string>  $siteMap
     * @param  array<string, string>  $clientMap
     * @param  array<int, string>  $targetMap
     */
    public function formatSession(array $session, array $siteMap, array $clientMap, array $targetMap): array
    {
        $who = $clientMap[$session['src_ip']] ?? $session['src_ip'];
        $siteName = $siteMap[$session['resource_id']] ?? "site#{$session['resource_id']}";
        $resourceName = $targetMap[$session['resource_id']] ?? null;
        $where = $resourceName ?: $siteName;

        return [
            'started' => $session['started'],
            'ended' => $session['ended'] ?? 'ongoing',
            'duration' => $session['duration'] ?? '—',
            'who' => $who,
            'where' => $where,
            'proto' => strtoupper($session['proto']),
            'dst' => "{$session['dst_ip']}:{$session['dst_port']}",
        ];
    }
}
