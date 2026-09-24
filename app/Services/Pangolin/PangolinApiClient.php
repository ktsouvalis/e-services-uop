<?php

namespace App\Services\Pangolin;

use Illuminate\Support\Facades\Http;

/**
 * PHP client for the Pangolin Integration API, ported from create_private_resources.py
 * / normalize_private_resources.py's shared request helpers (api_headers(),
 * get_all_pages(), verify_org(), get_sites(), etc. — see pangolin-utils'
 * CLAUDE.md for the Integration API's own background: a genuinely separate
 * backend process (port 3003 on the `pangolin` container on akropolis-2),
 * distinct from both the Next.js dashboard (port 3002) and the dashboard's
 * own session-cookie-authenticated "Dashboard API server" (port 3000, the
 * only one Traefik actually publishes externally, at /api/v1 on
 * pangolin.uop.gr — NOT this Integration API, even though both are
 * versioned under /v1 and a Bearer API key gets an identical generic 401
 * from port 3000 whether the key is valid, garbage, or absent). Port 3003
 * isn't exposed publicly at all (confirmed live 2026-09-23 after a Pangolin
 * node migration dropped the old /int-api Traefik rule that used to proxy
 * it) — reached instead via the `pangolin-tunnel` compose service's SSH
 * local-forward, so base_url is that container's own host:port and has no
 * path prefix at all; this class appends "/v1/..." itself.
 */
class PangolinApiClient
{
    private function baseUrl(): string
    {
        return rtrim(config('pangolin.base_url'), '/');
    }

    private function orgSlug(): string
    {
        return config('pangolin.org_slug');
    }

    private function http()
    {
        // Without an explicit timeout every call falls back to Laravel's 30s
        // default, so a stalled tunnel turned one import into many minutes of
        // hanging instead of failing fast.
        return Http::withToken(config('pangolin.api_key'))
            ->connectTimeout(config('pangolin.http_timeout'))
            ->timeout(config('pangolin.http_timeout'));
    }

    /** GET /v1/org/{orgSlug} — sanity-checks the org/api key before anything else. */
    public function verifyOrg(): array
    {
        return $this->http()->get("{$this->baseUrl()}/v1/org/{$this->orgSlug()}")->throw()->json('data');
    }

    /**
     * Every private resource this tooling creates spans every site in the
     * org (via siteIds) for HA — same call create_/normalize_ both make.
     */
    public function listSites(): array
    {
        return $this->getAllPages("/org/{$this->orgSlug()}/sites", 'sites');
    }

    public function listUsers(): array
    {
        return $this->getAllPages("/org/{$this->orgSlug()}/users", 'users');
    }

    /**
     * sanitized local-part -> [[email, userId], ...] across every org user —
     * ported from normalize_private_resources.py's build_org_email_index().
     * Used by both Import and Normalize. Groups by *sanitized* local part
     * and keeps every match, since more than one org account can share one
     * (see NormalizeResolver's own EMAIL_DOMAIN_PRIORITY tie-break for why).
     */
    public function buildOrgEmailIndex(): array
    {
        $index = [];
        foreach ($this->listUsers() as $u) {
            // User rows show up flat ({email, id}) or nested under {user:
            // {email, id}} depending on the endpoint/Pangolin version.
            $email = strtolower((string) ($u['email'] ?? $u['user']['email'] ?? ''));
            $uid = $u['id'] ?? $u['user']['id'] ?? null;
            if ($email === '' || $uid === null) {
                continue;
            }
            $index[ResourceNaming::sanitizeUsername($email)][] = [$email, $uid];
        }

        return $index;
    }

    /** GET /v1/site-resource/{id}/users */
    public function getResourceUsers(int $siteResourceId): array
    {
        return $this->http()
            ->get("{$this->baseUrl()}/v1/site-resource/{$siteResourceId}/users")
            ->throw()->json('data.users') ?? [];
    }

    /** GET /v1/site-resource/{id}/roles */
    public function getResourceRoles(int $siteResourceId): array
    {
        return $this->http()
            ->get("{$this->baseUrl()}/v1/site-resource/{$siteResourceId}/roles")
            ->throw()->json('data.roles') ?? [];
    }

    /**
     * GET /v1/site-resource/{id}/clients — never reported/checked (every
     * resource observed has had zero), fetched only to echo `clientIds`
     * back unchanged on updateSiteResource() calls (same reasoning as
     * tcpPortRangeString/udpPortRangeString, see its own docblock).
     */
    public function getResourceClients(int $siteResourceId): array
    {
        return $this->http()
            ->get("{$this->baseUrl()}/v1/site-resource/{$siteResourceId}/clients")
            ->throw()->json('data.clients') ?? [];
    }

    /** POST /v1/site-resource/{id}/users — replaces the resource's access list wholesale. */
    public function setResourceUsers(int $siteResourceId, array $userIds): void
    {
        $this->http()
            ->post("{$this->baseUrl()}/v1/site-resource/{$siteResourceId}/users", ['userIds' => $userIds])
            ->throw();
    }

    /**
     * Resolve a mix of numeric siteResourceIds and niceIds into numeric
     * siteResourceIds. Tokens that are already numeric pass through as-is;
     * anything else is looked up by niceId against the live resource list.
     */
    public function resolveSiteResourceIds(array $tokens): array
    {
        $numeric = [];
        $byNiceId = [];
        foreach ($tokens as $token) {
            if (is_numeric($token)) {
                $numeric[] = (int) $token;
            } else {
                $byNiceId[] = $token;
            }
        }

        if (empty($byNiceId)) {
            return $numeric;
        }

        $resources = $this->listSiteResources();
        foreach ($byNiceId as $niceId) {
            $match = collect($resources)->firstWhere('niceId', $niceId);
            if ($match) {
                $numeric[] = (int) $match['siteResourceId'];
            }
        }

        return array_values(array_unique($numeric));
    }

    public function listSiteResources(): array
    {
        return $this->getAllPages("/org/{$this->orgSlug()}/site-resources", 'siteResources');
    }

    /**
     * PUT /v1/org/{orgSlug}/site-resource — the create endpoint's schema
     * rejects an "enabled" key outright (400 "Unrecognized key"), unlike the
     * update endpoint. Callers must never include it here; set it via a
     * follow-up updateSiteResource() call once the resource exists (see
     * create_site_resource()'s own comment in create_private_resources.py).
     * Returns the created resource's data (siteResourceId, niceId, ...).
     */
    public function createSiteResource(array $payload): array
    {
        return $this->http()
            ->put("{$this->baseUrl()}/v1/org/{$this->orgSlug()}/site-resource", $payload)
            ->throw()
            ->json('data');
    }

    /**
     * POST /v1/site-resource/{id} — confirmed live (see CLAUDE.md) that
     * omitting tcpPortRangeString/udpPortRangeString/disableIcmp doesn't
     * leave them alone, it resets udpPortRangeString back to "*" (all) on
     * the resource being edited. Callers that only intend to change one
     * field (e.g. just "enabled") must still echo back the resource's own
     * current values for the others explicitly — this method doesn't do
     * that merging itself, same as the Python original's update_resource().
     */
    public function updateSiteResource(int $siteResourceId, array $fields): void
    {
        $this->http()
            ->post("{$this->baseUrl()}/v1/site-resource/{$siteResourceId}", $fields)
            ->throw();
    }

    private function getAllPages(string $path, string $dataKey, int $pageSize = 1000): array
    {
        $items = [];
        $page = 1;

        do {
            $response = $this->http()
                ->get("{$this->baseUrl()}/v1{$path}", ['pageSize' => $pageSize, 'page' => $page])
                ->throw()
                ->json();

            $pageItems = $response['data'][$dataKey] ?? [];
            $items = array_merge($items, $pageItems);
            $total = $response['data']['pagination']['total'] ?? count($items);
            $page++;
        } while (count($items) < $total && ! empty($pageItems));

        return $items;
    }
}
