<?php

namespace App\Services\Pangolin;

/**
 * Pure target-resolution logic ported from normalize_private_resources.py —
 * no API calls here, just the naming-convention reversal/matching rules.
 * See that script's own module docstring (and CLAUDE.md's Pangolin section)
 * for the full rationale; kept deliberately free of side effects so it can
 * be unit-tested without HTTP mocking.
 */
class NormalizeResolver
{
    /**
     * Some org accounts exist twice under the same sanitized local part on
     * different domains (confirmed live: ktsouvalis@uop.gr / ktsouvalis@go.uop.gr
     * both sanitize to "ktsouvalis") — lower index = higher priority.
     */
    private const EMAIL_DOMAIN_PRIORITY = ['uop.gr', 'go.uop.gr'];

    /**
     * From a list of [email, userId] pairs that all share one sanitized
     * local part, return the one to use, or [null, null] if still ambiguous.
     * A single candidate is used as-is. Multiple candidates are resolved via
     * EMAIL_DOMAIN_PRIORITY, but only when that preference is itself
     * unambiguous (exactly one candidate on the best-ranked domain present) —
     * still refuses to guess if two candidates tie on the same domain rank.
     *
     * @param  array<int, array{0: string, 1: int}>  $candidates
     */
    public function pickUniqueCandidate(array $candidates): array
    {
        if (count($candidates) === 1) {
            return $candidates[0];
        }
        if (! $candidates) {
            return [null, null];
        }

        $domainRank = function (string $email): int {
            $parts = explode('@', $email, 2);
            $domain = strtolower($parts[1] ?? '');
            $idx = array_search($domain, self::EMAIL_DOMAIN_PRIORITY, true);

            return $idx === false ? count(self::EMAIL_DOMAIN_PRIORITY) : $idx;
        };

        $bestRank = min(array_map(fn ($c) => $domainRank($c[0]), $candidates));
        $best = array_values(array_filter($candidates, fn ($c) => $domainRank($c[0]) === $bestRank));

        return count($best) === 1 ? $best[0] : [null, null];
    }

    /**
     * Strict reverse of create_private_resources.py's naming formula, for a
     * resource with 0 users currently assigned. Peels the known city and
     * known vlan/tail off the name; if what's left is exactly one org
     * user's sanitized local part, that's not a guess, so it's safe to
     * auto-grant. [null, null] if the name isn't an exact convention match
     * or the leftover doesn't uniquely resolve.
     *
     * @param  array<string, array<int, array{0: string, 1: int}>>  $orgEmailIndex
     */
    public function resolveUnassignedTarget(?string $name, string $city, string $vlan, string $tail, array $orgEmailIndex): array
    {
        if (! $name) {
            return [null, null];
        }
        $lname = strtolower($name);
        $prefix = "{$city}-";
        $suffix = "-{$vlan}-{$tail}";
        if (! str_starts_with($lname, $prefix) || ! str_ends_with($lname, $suffix)) {
            return [null, null];
        }
        $candidateUsername = substr($lname, strlen($prefix), strlen($lname) - strlen($prefix) - strlen($suffix));
        if ($candidateUsername === '' || str_contains($candidateUsername, '-')) {
            return [null, null];
        }

        return $this->pickUniqueCandidate($orgEmailIndex[$candidateUsername] ?? []);
    }

    /**
     * Mirror-image reversal of ResourceNaming::expectedNiceIdWithUdp(), for
     * a resource with 0 users currently assigned. Peels the known
     * vlan/tail/tcpPorts/udpPorts off the *end* of niceId (it has no city
     * segment to anchor a prefix peel). [null, null] if niceId isn't an
     * exact match, either port list couldn't be computed, or the leftover
     * doesn't uniquely resolve. Both port lists must be known (non-null) to
     * attempt this at all — a resource whose TCP or UDP range is a wildcard
     * has no discrete ports segment to reverse against.
     *
     * @param  string[]|null  $tcpPorts
     * @param  string[]|null  $udpPorts
     * @param  array<string, array<int, array{0: string, 1: int}>>  $orgEmailIndex
     */
    public function resolveUnassignedTargetFromNiceId(?string $niceId, string $vlan, string $tail, ?array $tcpPorts, ?array $udpPorts, array $orgEmailIndex): array
    {
        if (! $niceId || $tcpPorts === null || $udpPorts === null) {
            return [null, null];
        }
        $suffix = '-'.implode('-', [
            $vlan, $tail,
            ...array_map(fn ($p) => "p{$p}", $tcpPorts),
            ...array_map(fn ($p) => "u{$p}", $udpPorts),
        ]);
        $lnice = strtolower($niceId);
        if (! str_ends_with($lnice, $suffix)) {
            return [null, null];
        }
        $candidateUsername = substr($lnice, 0, strlen($lnice) - strlen($suffix));
        if ($candidateUsername === '' || str_contains($candidateUsername, '-')) {
            return [null, null];
        }

        return $this->pickUniqueCandidate($orgEmailIndex[$candidateUsername] ?? []);
    }

    /**
     * Loosest resolution: exactly one org user's sanitized local part
     * appears as a standalone "-"-separated segment of the name (not
     * necessarily in the specific city/vlan/tail positions the strict
     * reconstruction requires). [null, null] if zero or multiple org users
     * match. Each segment is resolved independently via
     * pickUniqueCandidate() — NOT a single call over every candidate pooled
     * across all segments — specifically so the domain-priority tie-break
     * only ever breaks a tie between two accounts of the *same* username,
     * never a genuine ambiguity between two different people matching two
     * different segments (that must still come back ambiguous).
     *
     * @param  array<string, array<int, array{0: string, 1: int}>>  $orgEmailIndex
     */
    public function findUniqueSegmentMatch(?string $name, array $orgEmailIndex): array
    {
        if (! $name) {
            return [null, null];
        }
        $segments = array_unique(explode('-', strtolower($name)));
        $candidates = [];
        foreach ($segments as $seg) {
            $c = $this->pickUniqueCandidate($orgEmailIndex[$seg] ?? []);
            if ($c !== [null, null]) {
                $candidates[serialize($c)] = $c;
            }
        }
        $unique = array_values($candidates);
        sort($unique);

        return count($unique) === 1 ? $unique[0] : [null, null];
    }

    /**
     * Return [targetEmail, targetUserId, reasonIfUnresolved, suggestedEmail].
     * Only the first two are meaningful when resolution succeeds (reason is
     * null); the last is only populated when resolution fails with 0 users.
     *
     * @param  array<int, array{userId: ?int, email: ?string, username: ?string}>  $users
     * @param  array<string, array<int, array{0: string, 1: int}>>  $orgEmailIndex
     */
    public function resolveTarget(?string $name, array $users, array $orgEmailIndex): array
    {
        $nameSegments = $name ? array_unique(explode('-', strtolower($name))) : [];
        $resolved = array_values(array_filter(array_map(
            fn ($u) => $u['email'] ? [$u['email'], $u['userId']] : null,
            $users
        )));

        if (count($users) === 0) {
            [$suggestedEmail] = $this->findUniqueSegmentMatch($name, $orgEmailIndex);

            return [null, null, 'no users currently assigned', $suggestedEmail];
        }

        if (count($resolved) === 1 && count($users) === 1) {
            [$email, $uid] = $resolved[0];

            return [strtolower($email), $uid, null, null];
        }

        $matches = array_values(array_filter($resolved, fn ($r) => in_array(ResourceNaming::sanitizeUsername($r[0]), $nameSegments, true)));
        if (count($matches) === 1) {
            [$email, $uid] = $matches[0];

            return [strtolower($email), $uid, null, null];
        }

        $emailsDesc = implode(', ', array_map(
            fn ($u) => $u['email'] ?: '<no-email:'.($u['username'] ?? $u['userId']).'>',
            $users
        ));

        return [null, null, count($users)." users assigned, ambiguous ({$emailsDesc})", null];
    }
}
