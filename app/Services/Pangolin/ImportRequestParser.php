<?php

namespace App\Services\Pangolin;

use Carbon\Carbon;

/**
 * Ported from create_private_resources.py's parse_row(). Each "Requests"
 * sheet row (Name/city, Destination, Ports, Alias, User Emails, Notes) can
 * produce zero or more resolved create-resource requests (one per User
 * Emails entry) plus zero or more local FAIL results (no API call made) —
 * see the module docstring in the Python original for the full naming
 * convention this implements.
 */
class ImportRequestParser
{
    public function __construct(private readonly NormalizeResolver $resolver)
    {
    }

    /**
     * @param  array<int, mixed>  $row  [city, destination, ports, alias, emails, notes]
     * @param  array<string, array<int, array{0: string, 1: int}>>  $orgEmailIndex  PangolinApiClient::buildOrgEmailIndex()
     * @return array{0: array<int, array>, 1: array<int, array>} [resolvedRequests, failResults]
     */
    public function parseRow(int $rowNum, array $row, array $orgEmailIndex): array
    {
        [$city, $destination, $portsValue, $alias, $emails, $notes] = array_pad($row, 6, null);

        if (! $destination) {
            return [[], []];
        }

        $destination = trim((string) $destination);
        $mode = str_contains($destination, '/') ? 'cidr' : 'host';
        $alias = $alias ? trim((string) $alias) : null;
        $portsDisplay = $portsValue ? trim((string) $portsValue) : '';
        $tcpPorts = ResourceNaming::parseTcpPorts($portsValue);
        $city = $city ? trim((string) $city) : '';
        $notesVal = $notes ? trim((string) $notes) : null;
        [$vlan, $tail] = ResourceNaming::namingSegments($destination, $mode);

        $rawEmails = array_values(array_filter(array_map('trim', explode(',', (string) ($emails ?? ''))), fn ($e) => $e !== ''));

        $makeFail = fn (?string $email, string $error) => [
            'row_num' => $rowNum, 'city' => $city, 'name' => null, 'destination' => $destination,
            'alias' => $alias, 'tcp_ports' => $tcpPorts, 'email' => $email, 'notes' => $notesVal,
            'sites' => null, 'status' => 'FAIL', 'nice_id' => null, 'enabled' => null,
            'timestamp' => Carbon::now()->format('Y-m-d H:i:s'), 'error' => $error,
        ];

        if (! $rawEmails) {
            return [[], [$makeFail(null, 'no User Emails provided')]];
        }

        $resolvedReqs = [];
        $failResults = [];

        foreach ($rawEmails as $rawEmail) {
            $email = strtolower($rawEmail);

            if ($tcpPorts === null) {
                $failResults[] = $makeFail($rawEmail,
                    "invalid/missing Ports '{$portsDisplay}' (expected comma-separated TCP ports and/or ranges, e.g. \"22,3389,32555-32590\")");

                continue;
            }
            if ($vlan === null) {
                $failResults[] = $makeFail($rawEmail,
                    "destination '{$destination}' is not a valid 10.x.y.z IPv4 host/CIDR (or the CIDR mask is too broad to name a single owner's slice)");

                continue;
            }

            $username = ResourceNaming::sanitizeUsername($email);
            $resourceName = implode('-', [strtolower($city), $username, $vlan, $tail]);
            [$matchedEmail, $userId] = $this->resolveUser($email, $username, $orgEmailIndex);
            $req = [
                '_row_num' => $rowNum,
                '_city' => $city,
                '_notes' => $notesVal,
                '_email' => $rawEmail,
                '_user_resolved' => $userId !== null,
                '_matched_email' => $matchedEmail,
                'name' => $resourceName,
                'mode' => $mode,
                'destination' => $destination,
                'tcpPortRangeString' => $tcpPorts,
                'udpPortRangeString' => '',
                'disableIcmp' => true,
                'roleIds' => [],
                'clientIds' => [],
                'userIds' => $userId !== null ? [$userId] : [],
                // Enabled only when a live org account was actually matched
                // — a resource created for an unmatched email stays disabled
                // until Normalize backfills access.
                'enabled' => $userId !== null,
            ];
            // No match → no niceId sent, so Pangolin keeps its own generated
            // (random) default instead of one claiming an owner that doesn't
            // exist yet. Normalize sets the real one once the user is found.
            if ($userId !== null) {
                $req['niceId'] = ResourceNaming::expectedNiceId($username, $vlan, $tail, explode(',', $tcpPorts));
            }
            if ($alias) {
                $req['alias'] = $alias;
            }
            $resolvedReqs[] = $req;
        }

        return [$resolvedReqs, $failResults];
    }

    /**
     * Exact email match first; otherwise the org user whose sanitized local
     * part equals the resource name's username segment (e.g. "costas" in
     * tripoli-costas-1529-201), with NormalizeResolver's domain tie-break.
     *
     * @return array{0: ?string, 1: ?int} [matchedEmail, userId]
     */
    private function resolveUser(string $email, string $username, array $orgEmailIndex): array
    {
        foreach ($orgEmailIndex[$username] ?? [] as [$candidateEmail, $uid]) {
            if ($candidateEmail === $email) {
                return [$candidateEmail, $uid];
            }
        }

        return $this->resolver->pickUniqueCandidate($orgEmailIndex[$username] ?? []);
    }
}
