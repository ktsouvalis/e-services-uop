<?php

namespace App\Services\Pangolin;

/**
 * Picks the single Pangolin site a private resource belongs on — every
 * resource Import creates or Normalize edits/splits gets exactly one site,
 * not every org site. Destination's first two octets win
 * (pangolin.site_routing.by_prefix); only when those don't match does the
 * city (Import's column 1 / the resource name's first segment) decide
 * (pangolin.site_routing.by_city). Site names are matched case-insensitively
 * against the org's live listSites() names.
 */
class SiteRouting
{
    /** The configured site name for this destination/city, or null if neither maps. */
    public static function siteNameFor(?string $destination, ?string $city): ?string
    {
        $octets = explode('.', explode('/', trim((string) $destination))[0]);
        if (count($octets) >= 2) {
            $prefix = "{$octets[0]}.{$octets[1]}";
            $byPrefix = config('pangolin.site_routing.by_prefix', []);
            if (isset($byPrefix[$prefix])) {
                return $byPrefix[$prefix];
            }
        }

        return config('pangolin.site_routing.by_city', [])[strtolower(trim((string) $city))] ?? null;
    }

    /**
     * @param  array<int, array{siteId: int, name: string}>  $sites
     * @return array{0: ?array, 1: ?string} [site, error] — exactly one is null
     */
    public static function pick(array $sites, ?string $destination, ?string $city): array
    {
        $siteName = self::siteNameFor($destination, $city);
        if ($siteName === null) {
            return [null, "no site for destination '{$destination}' / city '{$city}' -- destination isn't in a mapped 10.x range and the city isn't a mapped one"];
        }

        foreach ($sites as $site) {
            if (strcasecmp(trim((string) $site['name']), $siteName) === 0) {
                return [$site, null];
            }
        }

        return [null, "mapped site '{$siteName}' not found among the org's sites"];
    }
}
