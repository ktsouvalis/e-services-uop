<?php

namespace App\Services\Pangolin;

use Carbon\Carbon;
use Illuminate\Http\Client\RequestException;

/**
 * Ported from create_private_resources.py's create_site_resource(). Creates
 * one site resource per resolved request (each row's User Emails entry —
 * see ImportRequestParser), spanning every org site for HA, and returns a
 * report row matching the Python original's Results-sheet fields exactly.
 */
class ImportResourceCreator
{
    public function __construct(private readonly PangolinApiClient $api)
    {
    }

    /**
     * @param  array<int, array{siteId: int, name: string}>  $sites
     */
    public function create(array $sites, array $req, bool $dryRun): array
    {
        $payload = collect($req)->reject(fn ($v, $k) => str_starts_with($k, '_'))->all();
        $payload['siteIds'] = array_map(fn ($s) => $s['siteId'], $sites);
        $siteNames = implode(', ', array_map(fn ($s) => $s['name'], $sites));
        $noUser = ! $req['_user_resolved'];

        $result = [
            'row_num' => $req['_row_num'],
            'city' => $req['_city'],
            'name' => $req['name'],
            'destination' => $req['destination'],
            'alias' => $req['alias'] ?? null,
            'tcp_ports' => $req['tcpPortRangeString'] ?: null,
            'email' => $req['_email'],
            'notes' => $req['_notes'],
            'sites' => $siteNames,
            'status' => null,
            // Set deterministically at request-build time — shown here
            // already for a dry run; overwritten with the API's own echoed
            // value below once a live create actually succeeds.
            'nice_id' => $req['niceId'] ?? null,
            'enabled' => $req['enabled'],
            'timestamp' => Carbon::now()->format('Y-m-d H:i:s'),
            'error' => match (true) {
                $noUser => "no user match: '{$req['_email']}' (username '".ResourceNaming::sanitizeUsername($req['_email'])."') not found among org users -- resource created disabled, without access and with Pangolin's default niceId; backfill later via Normalize",
                $req['_matched_email'] !== strtolower($req['_email']) => "matched org user '{$req['_matched_email']}' by username",
                default => null,
            },
        ];

        if ($dryRun) {
            $result['status'] = $noUser ? 'DRY-RUN_NO_USER' : 'DRY-RUN';

            return $result;
        }

        // The create endpoint's schema doesn't accept "enabled" — unlike the
        // update endpoint, it 400s with "Unrecognized key" if present. Set
        // it via a follow-up update call instead, once the resource exists.
        $createPayload = collect($payload)->except('enabled')->all();

        try {
            $body = $this->api->createSiteResource($createPayload);
        } catch (RequestException $e) {
            $result['status'] = 'FAIL';
            $result['error'] = "{$e->response->status()}: ".substr($e->response->body(), 0, 500);

            return $result;
        }

        $result['status'] = $noUser ? 'OK_NO_USER' : 'OK';
        $result['nice_id'] = $body['niceId'] ?? $result['nice_id'];

        try {
            $this->api->updateSiteResource($body['siteResourceId'], ['enabled' => $req['enabled']]);
        } catch (RequestException $e) {
            $warn = "created OK but failed to set enabled={$req['enabled']}: ".
                "{$e->response->status()}: ".substr($e->response->body(), 0, 500).
                ' -- backfill later via normalize_private_resources';
            $result['error'] = $result['error'] ? "{$result['error']}; {$warn}" : $warn;
        }

        return $result;
    }
}
