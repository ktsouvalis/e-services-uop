<?php

namespace App\Services\Pangolin;

use PDO;

/**
 * Ported from logs_viewer.py's get_pg_connection()/build_lookup_maps()/
 * format_session() — resolves Newt ACCESS log sessions (raw IPs/site IDs)
 * into human-readable identities via a direct Postgres connection to the
 * cluster's own database (config('pangolin.postgres.*') — reached through
 * the pangolin-tunnel compose service's SSH bastion, see docker-compose.yml
 * and config/pangolin.php). Needs the pdo_pgsql extension (see both
 * Dockerfiles).
 *
 * If the connection fails, callers are expected to catch and fall back to
 * raw IPs/IDs — resolve() already does this gracefully when the maps are
 * simply empty — matching logs_viewer.py's own documented fallback
 * behavior, not a bug to "fix" by failing the whole sync over a Postgres
 * reachability problem. See CLAUDE.md's Pangolin module section.
 */
class NewtAccessLogResolver
{
    public function connect(): PDO
    {
        $host = config('pangolin.postgres.host');
        $port = config('pangolin.postgres.port');
        $db = config('pangolin.postgres.database');
        $user = config('pangolin.postgres.username');
        $password = config('pangolin.postgres.password');

        return new PDO(
            "pgsql:host={$host};port={$port};dbname={$db};connect_timeout=10",
            $user,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }

    /**
     * Returns [siteMap (siteId -> name), clientMap (ip -> {user_name,
     * user_email, client_name}), targetMap (siteResourceId -> name)].
     *
     * @return array{0: array<int, string>, 1: array<string, array{user_name: ?string, user_email: ?string, client_name: string}>, 2: array<int, string>}
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
            $clientMap[$ip] = [
                'user_name' => $row['user_name'] ?: null,
                'user_email' => $row['user_email'] ?: null,
                'client_name' => $row['name'],
            ];
        }

        $targetMap = [];
        foreach ($pdo->query('SELECT "siteResourceId", name FROM "siteResources"')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $targetMap[(int) $row['siteResourceId']] = $row['name'];
        }

        return [$siteMap, $clientMap, $targetMap];
    }

    /**
     * Resolve a parsed session (see NewtAccessLogParser) into the
     * identity/naming columns pangolin_newt_connections stores. The
     * "resource=" field in Newt's ACCESS log lines is a siteResourceId
     * (confirmed live 2026-09-23 — every fetched row resolves via
     * $targetMap). There is deliberately no per-resource "site" resolution
     * here: `siteResources."networkId"` always equals the resource's own
     * siteResourceId (confirmed: 0 mismatches across the whole table on a
     * real cluster) — it is NOT a foreign key to sites.siteId. Every
     * private resource spans every org site simultaneously for HA (see the
     * `siteNetworks` many-to-many table), so there is no single "the site"
     * for a resource to look up at all. NewtConnectionSync derives
     * site_name separately, from which Newt agent's log the session came
     * from (each agent IS one specific site's exit node) — see its own
     * docblock. A direct $siteMap lookup on resource_id is kept here only
     * as a last-resort fallback for a resource that didn't resolve via
     * $targetMap at all, in case some other Newt session shape really does
     * log a raw siteId there.
     *
     * @param  array<int, string>  $siteMap
     * @param  array<string, array{user_name: ?string, user_email: ?string, client_name: string}>  $clientMap
     * @param  array<int, string>  $targetMap
     * @return array{user_name: ?string, user_email: ?string, client_name: ?string, site_name: ?string, resource_name: ?string}
     */
    public function resolve(array $session, array $siteMap, array $clientMap, array $targetMap): array
    {
        $client = $clientMap[$session['src_ip']] ?? null;
        $resourceName = $targetMap[$session['resource_id']] ?? null;
        $siteName = $siteMap[$session['resource_id']] ?? null;

        return [
            'user_name' => $client['user_name'] ?? null,
            'user_email' => $client['user_email'] ?? null,
            'client_name' => $client['client_name'] ?? null,
            'site_name' => $siteName,
            'resource_name' => $resourceName,
        ];
    }
}
