<?php

namespace App\Services\Pangolin;

/**
 * Naming/niceId computation ported from pangolin-utils' create_private_resources.py
 * and normalize_private_resources.py, which each had an identical copy of
 * these functions ("kept in sync deliberately" per their own docstrings,
 * since normalize_ audits/fixes whatever create_ produces). Shared here
 * instead, but the formulas themselves are unchanged from the Python
 * originals — see CLAUDE.md's Pangolin module section before touching this.
 */
class ResourceNaming
{
    /**
     * 10.x.y.z host -> [vlan, tail], e.g. 10.23.2.50 -> ["2302", "50"].
     * vlan = x concatenated with y zero-padded to 2 digits; tail is the 4th
     * octet, unpadded. [null, null] if not 4 numeric octets or not 10.x.y.z.
     */
    public static function vlanZ(string $destination): array
    {
        $parts = explode('.', trim($destination));
        if (count($parts) !== 4 || ! self::allDigits($parts) || $parts[0] !== '10') {
            return [null, null];
        }
        [, $x, $y, $z] = $parts;

        return [sprintf('%d%02d', (int) $x, (int) $y), (string) (int) $z];
    }

    /**
     * 10.x.y.z/prefix -> [vlan, tail]. vlan is built from whichever octets
     * the prefix actually fixes (dropped entirely below /16); tail is "all"
     * for the common /24 case or the literal prefix length otherwise (e.g.
     * "/16" -> tail "16"). [null, null] if the mask is narrower than /16.
     */
    public static function cidrVlanTail(string $destination): array
    {
        [$ipPart, $sep, $prefixStr] = self::partition(trim($destination), '/');
        if ($sep === '' || ! ctype_digit($prefixStr)) {
            return [null, null];
        }
        $prefix = (int) $prefixStr;
        $parts = explode('.', $ipPart);
        if (count($parts) !== 4 || ! self::allDigits($parts) || $parts[0] !== '10') {
            return [null, null];
        }
        [, $x, $y, $z] = $parts;

        $fixedOctets = max(0, min(3, intdiv($prefix, 8) - 1));
        if ($fixedOctets === 0) {
            return [null, null];
        }

        $vlanTokens = [(string) (int) $x];
        if ($fixedOctets >= 2) {
            $vlanTokens[] = sprintf('%02d', (int) $y);
        }
        if ($fixedOctets >= 3) {
            $vlanTokens[] = (string) (int) $z;
        }
        $tail = $prefix === 24 ? 'all' : (string) $prefix;

        return [implode('', $vlanTokens), $tail];
    }

    /** Dispatch to vlanZ()/cidrVlanTail() for mode "host"/"cidr". */
    public static function namingSegments(string $destination, string $mode): array
    {
        return match ($mode) {
            'host' => self::vlanZ($destination),
            'cidr' => self::cidrVlanTail($destination),
            default => [null, null],
        };
    }

    /**
     * Sort key for a plain port ("22") or a "start-end" range
     * ("32555-32590") — the numeric start of either.
     */
    public static function portSortKey(string $token): int
    {
        return (int) strtok($token, '-');
    }

    /**
     * "<username>-<vlan>-<tail>-<ports>", ports as sorted "pNNN" tokens. A
     * range token like "32555-32590" keeps its single "p" prefix over the
     * whole range ("p32555-32590"), NOT "p32555-p32590", since it's never
     * split apart, just prefixed as-is. TCP-only — used by Import (whose
     * request sheet has no UDP column, UDP is always blocked at create
     * time) and kept byte-for-byte unchanged for that reason. Normalize
     * uses expectedNiceIdWithUdp() instead, see its own docblock.
     *
     * @param  string[]  $ports
     */
    public static function expectedNiceId(string $username, string $vlan, string $tail, array $ports): string
    {
        return self::expectedNiceIdWithUdp($username, $vlan, $tail, $ports, []);
    }

    /**
     * Same as expectedNiceId(), but also appends any UDP ports as sorted
     * "uNNN" tokens after the TCP "pNNN" ones — e.g. tcp=[22,3389] +
     * udp=[53] -> "<username>-<vlan>-<tail>-p22-p3389-u53". A resource with
     * no UDP ports (the common case — most private resources still block
     * UDP entirely) produces an identical niceId to expectedNiceId() alone,
     * so this is purely additive, not a reinterpretation of the existing
     * TCP-only convention. Added 2026-09-22 for normalize_private_resources'
     * native port — see CLAUDE.md's Pangolin module section; Import doesn't
     * use this (no UDP Ports column in its request sheet today).
     *
     * @param  string[]  $tcpPorts
     * @param  string[]  $udpPorts
     */
    public static function expectedNiceIdWithUdp(string $username, string $vlan, string $tail, array $tcpPorts, array $udpPorts): string
    {
        $segments = [$username, $vlan, $tail];
        foreach (self::sortedByPort($tcpPorts) as $p) {
            $segments[] = "p{$p}";
        }
        foreach (self::sortedByPort($udpPorts) as $p) {
            $segments[] = "u{$p}";
        }

        return implode('-', $segments);
    }

    /** @param  string[]  $ports  @return string[] */
    private static function sortedByPort(array $ports): array
    {
        $sorted = $ports;
        usort($sorted, fn ($a, $b) => self::portSortKey($a) <=> self::portSortKey($b));

        return $sorted;
    }

    /**
     * Parse the "Ports" column into a normalized comma-separated TCP port
     * string (tokens kept in their original order, not sorted — sorting only
     * ever happens for niceId), or null if missing/invalid. Each
     * comma-separated token is either a single port number (1-65535) or a
     * "start-end" range. UDP and ICMP are always blocked, so they're not a
     * user-configurable input.
     */
    public static function parseTcpPorts(mixed $rawValue): ?string
    {
        $raw = $rawValue !== null ? trim((string) $rawValue) : '';
        $tokens = array_values(array_filter(array_map('trim', explode(',', $raw)), fn ($t) => $t !== ''));
        if (! $tokens) {
            return null;
        }
        foreach ($tokens as $tok) {
            if (ctype_digit($tok)) {
                if ((int) $tok < 1 || (int) $tok > 65535) {
                    return null;
                }
                continue;
            }
            [$start, $sep, $end] = self::partition($tok, '-');
            if ($sep === '' || ! ctype_digit($start) || ! ctype_digit($end)) {
                return null;
            }
            if ((int) $start < 1 || (int) $start > (int) $end || (int) $end > 65535) {
                return null;
            }
        }

        return implode(',', $tokens);
    }

    /**
     * Parse a resource's own tcpPortRangeString into sorted port tokens for
     * niceId purposes, or null if it isn't a plain comma-separated list of
     * ports/ranges (e.g. a "*" wildcard) — niceId normalization is skipped
     * in that case, there's no discrete ports segment to compute. Unlike
     * parseTcpPorts(), this doesn't enforce the 1-65535 range (the value is
     * already a stored, presumably-valid resource, not raw user input) and
     * returns the tokens pre-sorted.
     *
     * @return string[]|null
     */
    public static function expectedPorts(?string $tcpPortRangeString): ?array
    {
        $raw = trim((string) $tcpPortRangeString);
        if ($raw === '') {
            return null;
        }
        $tokens = array_values(array_filter(array_map('trim', explode(',', $raw)), fn ($t) => $t !== ''));
        if (! $tokens) {
            return null;
        }
        foreach ($tokens as $tok) {
            if (ctype_digit($tok)) {
                continue;
            }
            [$start, $sep, $end] = self::partition($tok, '-');
            if ($sep === '' || ! ctype_digit($start) || ! ctype_digit($end) || (int) $start > (int) $end) {
                return null;
            }
        }
        usort($tokens, fn ($a, $b) => self::portSortKey($a) <=> self::portSortKey($b));

        return $tokens;
    }

    /** email local-part, lowercased, with dots stripped: "m.katsis@x" -> "mkatsis". */
    public static function sanitizeUsername(string $email): string
    {
        $local = explode('@', $email, 2)[0];

        return str_replace('.', '', strtolower(trim($local)));
    }

    /**
     * Mirrors Python's str.partition(sep): [before, sep, after], or
     * [whole_string, '', ''] if sep isn't found. PHP has no direct
     * equivalent — explode()+array_pad() is NOT the same thing (it loses
     * which piece is the separator vs. the tail, silently misparsing
     * anything with the separator present, which is exactly why this
     * exists after that mistake shipped once already).
     */
    private static function partition(string $s, string $sep): array
    {
        $pos = strpos($s, $sep);
        if ($pos === false) {
            return [$s, '', ''];
        }

        return [substr($s, 0, $pos), $sep, substr($s, $pos + strlen($sep))];
    }

    private static function allDigits(array $parts): bool
    {
        foreach ($parts as $p) {
            if (! ctype_digit($p)) {
                return false;
            }
        }

        return true;
    }
}
