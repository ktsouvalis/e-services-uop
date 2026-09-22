<?php

namespace App\Services\Pangolin;

use Carbon\Carbon;
use Illuminate\Http\Client\RequestException;

/**
 * Ported from normalize_private_resources.py's process_resource() — the
 * core per-resource audit/fix decision tree. See that script's own module
 * docstring (reproduced in detail in CLAUDE.md's Pangolin module section)
 * before changing anything here; this governs real network access grants.
 */
class NormalizeProcessor
{
    public function __construct(
        private readonly PangolinApiClient $api,
        private readonly NormalizeResolver $resolver,
    ) {
    }

    /**
     * @param  array<int, array{siteId: int, name: string}>  $sites
     * @param  array<string, array<int, array{0: string, 1: int}>>  $orgEmailIndex
     */
    public function process(array $sites, array $res, array $orgEmailIndex, bool $applyChanges): array
    {
        $resourceId = $res['siteResourceId'];
        $currentEnabled = $res['enabled'] ?? true;
        $row = [
            'resource_id' => $resourceId, 'nice_id' => $res['niceId'] ?? null, 'new_nice_id' => $res['niceId'] ?? null,
            'mode' => $res['mode'],
            'destination' => $res['destination'], 'city' => null, 'target_email' => null,
            'old_name' => $res['name'], 'new_name' => $res['name'], 'action' => 'none',
            'removed_users' => null, 'current_users' => null, 'roles' => null,
            'enabled' => $currentEnabled, 'new_enabled' => $currentEnabled,
            'status' => null, 'reason' => null,
            'timestamp' => Carbon::now()->format('Y-m-d H:i:s'),
        ];

        if (! in_array($res['mode'], ['host', 'cidr'], true)) {
            $row['status'] = 'SKIPPED';
            $row['reason'] = "mode='{$res['mode']}' -- only host/cidr resources are in scope";

            return $row;
        }

        [$vlan, $tail] = ResourceNaming::namingSegments($res['destination'], $res['mode']);
        if ($vlan === null) {
            $row['status'] = 'SKIPPED';
            $row['reason'] = "destination '{$res['destination']}' is not a 10.x.y.z {$res['mode']} range (or the mask is too broad to name a single owner's slice)";

            return $row;
        }

        $nameParts = $res['name'] ? explode('-', $res['name'], 2) : [];
        if (count($nameParts) < 2 || trim($nameParts[0]) === '') {
            $row['status'] = 'SKIPPED';
            $row['reason'] = "name '{$res['name']}' has no separable city segment";

            return $row;
        }
        $city = strtolower(trim($nameParts[0]));
        $row['city'] = $city;

        $tcpPorts = ResourceNaming::expectedPorts($res['tcpPortRangeString'] ?? null);
        // A blank/empty udpPortRangeString means "no UDP ports" (the common
        // case — most private resources still block UDP entirely), not
        // "unparseable" — treated as an empty list, not null, so it alone
        // never disables niceId enforcement the way a genuine wildcard
        // (e.g. "*") does. expectedPorts() itself can't tell those apart
        // (both are "" to it), so that distinction is made here instead.
        $rawUdp = trim((string) ($res['udpPortRangeString'] ?? ''));
        $udpPorts = $rawUdp === '' ? [] : ResourceNaming::expectedPorts($res['udpPortRangeString']);
        $portsKnown = $tcpPorts !== null && $udpPorts !== null;

        $users = $this->api->getResourceUsers($resourceId);
        $row['current_users'] = implode(', ', array_map(
            fn ($u) => $u['email'] ?: '<no-email:'.($u['username'] ?? '').'>',
            $users
        )) ?: '(none)';

        $roles = $this->api->getResourceRoles($resourceId);
        // Not reported/checked — fetched only to echo clientIds back
        // unchanged on any update below (same reasoning as tcp/udp/icmp).
        $clients = $this->api->getResourceClients($resourceId);
        $roleNames = collect($roles)->pluck('name')->sort()->values()->all();
        $row['roles'] = implode(', ', $roleNames) ?: '(none)';

        $notes = [];
        if (collect($roleNames)->contains(fn ($n) => $n !== 'Admin')) {
            $notes[] = 'unexpected role(s) attached (roles='.json_encode($roleNames).') -- not modified, review manually';
        }
        if (! $portsKnown) {
            $notes[] = "niceId not checked: tcpPortRangeString '".($res['tcpPortRangeString'] ?? '')."' / udpPortRangeString '".($res['udpPortRangeString'] ?? '')."' isn't a plain comma-separated list of ports/ranges";
        }

        if (count($users) === 0) {
            [$strictEmail, $strictUserId] = $this->resolver->resolveUnassignedTarget($res['name'], $city, $vlan, $tail, $orgEmailIndex);
            if (! $strictEmail) {
                [$strictEmail, $strictUserId] = $this->resolver->resolveUnassignedTargetFromNiceId($res['niceId'] ?? null, $vlan, $tail, $tcpPorts, $udpPorts, $orgEmailIndex);
            }
            if (! $strictEmail) {
                [$strictEmail, $strictUserId] = $this->resolver->findUniqueSegmentMatch($res['name'], $orgEmailIndex);
            }
        } else {
            $strictEmail = null;
            $strictUserId = null;
        }

        if ($strictEmail) {
            $targetEmail = $strictEmail;
            $targetUserId = $strictUserId;
            $unresolvedReason = null;
            $suggestion = null;
        } else {
            [$targetEmail, $targetUserId, $unresolvedReason, $suggestion] = $this->resolver->resolveTarget($res['name'], $users, $orgEmailIndex);
        }

        if ($unresolvedReason) {
            $reason = $unresolvedReason.($suggestion ? " -- org directory suggests: {$suggestion}" : '');
            if ($notes) {
                $reason .= '; '.implode('; ', $notes);
            }
            $row['reason'] = $reason;

            if (count($users) === 0 && $currentEnabled) {
                // No resolvable owner and still enabled — this resource's
                // baseline Admin-role default access shouldn't be reachable
                // by anyone while it sits unowned. Disable it until a future
                // run resolves a real target.
                $row['action'] = 'disable_no_user';
                if (($res['disableIcmp'] ?? null) !== true) {
                    $row['action'] .= '+block_icmp';
                }
                $row['new_enabled'] = false;
                if (! $applyChanges) {
                    $row['status'] = 'DRY-RUN';

                    return $row;
                }
                try {
                    $this->api->updateSiteResource($resourceId, [
                        'enabled' => false,
                        'userIds' => [],
                        'roleIds' => array_map(fn ($r) => $r['roleId'], $roles),
                        'clientIds' => array_map(fn ($c) => $c['clientId'], $clients),
                        'destination' => $res['destination'],
                        'siteIds' => $res['siteIds'],
                        'tcpPortRangeString' => $res['tcpPortRangeString'] ?? '',
                        'udpPortRangeString' => $res['udpPortRangeString'] ?? '',
                        // Actively enforced (always true), not just echoed
                        // back — ICMP is meant to always be blocked.
                        'disableIcmp' => true,
                    ]);
                    $row['status'] = 'OK';
                } catch (RequestException $e) {
                    $row['status'] = 'FAIL';
                    $row['reason'] = "{$e->response->status()}: ".substr($e->response->body(), 0, 400);
                }

                return $row;
            }

            // Already disabled (nothing to do) or ambiguous among 2+
            // already-assigned users — enabled is left alone, only the
            // 0-user axis ever touches it here.
            $row['status'] = 'SKIPPED';

            return $row;
        }

        $row['target_email'] = $targetEmail;
        $username = ResourceNaming::sanitizeUsername($targetEmail);
        $expectedName = "{$city}-{$username}-{$vlan}-{$tail}";
        $row['new_name'] = $expectedName;

        $expectedNiceId = $portsKnown ? ResourceNaming::expectedNiceIdWithUdp($username, $vlan, $tail, $tcpPorts, $udpPorts) : null;
        $row['new_nice_id'] = $expectedNiceId ?? ($res['niceId'] ?? null);

        $needsRename = $expectedName !== $res['name'];
        $needsNiceIdFix = $expectedNiceId !== null && $expectedNiceId !== ($res['niceId'] ?? '');
        $currentIds = collect($users)->pluck('userId')->filter()->unique()->values()->all();
        $needsAccessFix = $currentIds !== [$targetUserId];
        // A resolved target always means access is being granted or
        // confirmed — this resource should be (or become) enabled.
        $needsEnableFix = ! $currentEnabled;
        if ($needsEnableFix) {
            $row['new_enabled'] = true;
        }
        $needsIcmpFix = ($res['disableIcmp'] ?? null) !== true;

        // A user other than the resolved target no longer just loses access
        // when this resource gets pruned down to one owner — they get their
        // own new resource instead. A user with no resolvable email/userId
        // can't be split off and is still just pruned.
        $splitOffUsers = collect($users)
            ->filter(fn ($u) => ($u['userId'] ?? null) !== $targetUserId && ($u['email'] ?? null) && ($u['userId'] ?? null))
            ->map(fn ($u) => [strtolower($u['email']), $u['userId']])
            ->values()->all();
        $unsplittableUsers = collect($users)
            ->filter(fn ($u) => ($u['userId'] ?? null) !== $targetUserId && ! ($u['email'] ?? null))
            ->map(fn ($u) => $u['username'] ?? $u['userId'])
            ->values()->all();

        $removedDesc = array_map(fn ($su) => "{$su[0]} -> new resource", $splitOffUsers);
        $removedDesc = array_merge($removedDesc, array_map(fn ($u) => "<no-email:{$u}> -> dropped (no email to split to)", $unsplittableUsers));
        if ($removedDesc) {
            $row['removed_users'] = implode(', ', $removedDesc);
        }

        $reason = $notes ? implode('; ', $notes) : null;

        if (! ($needsRename || $needsNiceIdFix || $needsAccessFix || $needsEnableFix || $needsIcmpFix)) {
            $row['status'] = 'OK';
            $row['reason'] = $reason;

            return $row;
        }

        $actions = [];
        if ($needsRename) {
            $actions[] = 'rename';
        }
        if ($needsNiceIdFix) {
            $actions[] = 'fix_nice_id';
        }
        if ($needsIcmpFix) {
            $actions[] = 'block_icmp';
        }
        if ($needsEnableFix) {
            $actions[] = 'enable';
        }
        if ($needsAccessFix) {
            $actions[] = count($users) === 0 ? 'grant_access' : ($splitOffUsers ? 'split_access' : 'prune_access');
        }
        $row['action'] = implode('+', $actions);

        if (! $applyChanges) {
            $row['status'] = 'DRY-RUN';
            $row['reason'] = $reason;

            return $row;
        }

        try {
            // Split off every other user into their own resource *before*
            // touching the original — if any of these fails, the original
            // is left untouched rather than pruning someone's access with
            // nowhere for it to have gone.
            $created = [];
            foreach ($splitOffUsers as [$email, $userId]) {
                // Split resources always get udpPortRangeString forced blank
                // (see createSplitResource()'s own docblock) — its niceId is
                // correctly TCP-only via expectedNiceId(), not a regression
                // from the dual-protocol formula used for the resource being
                // audited above.
                $newRes = $this->createSplitResource($sites, $res, $city, $vlan, $tail, $tcpPorts, $email, $userId);
                $created[] = "{$email} -> siteResourceId={$newRes['siteResourceId']} niceId={$newRes['niceId']}";
            }
            if ($created) {
                $row['removed_users'] = implode(', ', array_merge($created,
                    array_map(fn ($u) => "<no-email:{$u}> -> dropped (no email to split to)", $unsplittableUsers)));
            }

            if ($needsRename || $needsNiceIdFix || $needsEnableFix || $needsIcmpFix) {
                $updateFields = [];
                if ($needsRename) {
                    $updateFields['name'] = $expectedName;
                }
                if ($needsNiceIdFix) {
                    $updateFields['niceId'] = $expectedNiceId;
                }
                // Despite the swagger schema showing every field optional,
                // this endpoint validates as if the whole resource were
                // being resubmitted. Echo back current values unchanged for
                // all of them (access, if it also needs fixing, is applied
                // separately below via the dedicated /users endpoint).
                $updateFields['userIds'] = collect($users)->pluck('userId')->filter()->values()->all();
                $updateFields['roleIds'] = array_map(fn ($r) => $r['roleId'], $roles);
                $updateFields['clientIds'] = array_map(fn ($c) => $c['clientId'], $clients);
                $updateFields['destination'] = $res['destination'];
                $updateFields['siteIds'] = $res['siteIds'];
                // Confirmed live Pangolin behavior: omitting tcp/udp on an
                // update doesn't leave them alone, it resets udp back to
                // "all". Always re-assert current values.
                $updateFields['tcpPortRangeString'] = $res['tcpPortRangeString'] ?? '';
                $updateFields['udpPortRangeString'] = $res['udpPortRangeString'] ?? '';
                // Unlike tcp/udp (only ever echoed back), disableIcmp is
                // actively enforced — always sent as true.
                $updateFields['disableIcmp'] = true;
                // Reaching this branch always means a target is resolved,
                // so the resource belongs enabled.
                $updateFields['enabled'] = true;
                $this->api->updateSiteResource($resourceId, $updateFields);
            }
            if ($needsAccessFix) {
                $this->api->setResourceUsers($resourceId, [$targetUserId]);
            }
            $row['status'] = 'OK';
            $row['reason'] = $reason;
        } catch (RequestException $e) {
            $row['status'] = 'FAIL';
            $row['reason'] = "{$e->response->status()}: ".substr($e->response->body(), 0, 400);
        }

        return $row;
    }

    /**
     * Create a new site-resource for a user being split off a multi-user
     * resource — same destination/TCP-ports/alias as the resource they're
     * being split from, spanning every org site, named/niceId'd for just
     * them. Unlike ImportResourceCreator's rows, the target user is already
     * fully resolved here, so niceId/enabled are both set immediately.
     * udpPortRangeString is always forced blank below (same as the Python
     * original), so $ports/niceId here are correctly TCP-only via
     * expectedNiceId() — this is consistent with the split's own actual
     * UDP state, not a regression from the dual-protocol formula used for
     * the resource being audited in process().
     */
    private function createSplitResource(array $sites, array $res, string $city, string $vlan, string $tail, ?array $ports, string $email, int $userId): array
    {
        $username = ResourceNaming::sanitizeUsername($email);
        $name = "{$city}-{$username}-{$vlan}-{$tail}";
        $payload = [
            'name' => $name,
            'mode' => $res['mode'],
            'destination' => $res['destination'],
            'tcpPortRangeString' => $res['tcpPortRangeString'] ?? '',
            'udpPortRangeString' => '',
            'disableIcmp' => true,
            'roleIds' => [],
            'clientIds' => [],
            'userIds' => [$userId],
            'siteIds' => array_map(fn ($s) => $s['siteId'], $sites),
        ];
        if ($ports !== null) {
            $payload['niceId'] = ResourceNaming::expectedNiceId($username, $vlan, $tail, $ports);
        }
        if (! empty($res['alias'])) {
            $payload['alias'] = $res['alias'];
        }

        // The create endpoint's schema doesn't accept "enabled" — set it via
        // a follow-up update once the resource exists. Target is already
        // fully resolved at this point, no reason for it to end up disabled.
        $newRes = $this->api->createSiteResource($payload);
        $this->api->updateSiteResource($newRes['siteResourceId'], ['enabled' => true]);

        return $newRes;
    }
}
