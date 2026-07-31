<?php

namespace App\Services\Pangolin;

use Illuminate\Support\Facades\Http;

/**
 * Minimal PHP client for the Pangolin Integration API — only what's needed
 * to resolve a niceId to the numeric siteResourceId normalize_private_resources.py's
 * --resource-id expects (see its get_site_resources()/api_headers() in pangolin-utils).
 */
class PangolinApiClient
{
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

    private function listSiteResources(): array
    {
        $baseUrl = config('pangolin.base_url');
        $orgSlug = config('pangolin.org_slug');
        $apiKey = config('pangolin.api_key');

        $items = [];
        $page = 1;
        $pageSize = 1000;

        do {
            $response = Http::withToken($apiKey)
                ->get("{$baseUrl}/v1/org/{$orgSlug}/site-resources", [
                    'pageSize' => $pageSize,
                    'page' => $page,
                ])
                ->throw()
                ->json();

            $pageItems = $response['data']['siteResources'] ?? [];
            $items = array_merge($items, $pageItems);
            $total = $response['data']['pagination']['total'] ?? count($items);
            $page++;
        } while (count($items) < $total && ! empty($pageItems));

        return $items;
    }
}
