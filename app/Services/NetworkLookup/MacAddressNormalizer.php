<?php

namespace App\Services\NetworkLookup;

/**
 * Single place that turns any input format (Huawei "xxxx-xxxx-xxxx", Cisco
 * "xxxx.xxxx.xxxx", colon-separated, or no separator, any case) into one
 * canonical lowercase colon-separated form - used by every parser and by
 * search input, so a MAC written differently by two sources always matches.
 */
class MacAddressNormalizer
{
    public static function normalize(string $raw): ?string
    {
        $hex = strtolower(preg_replace('/[^0-9a-fA-F]/', '', $raw));

        if (strlen($hex) !== 12) {
            return null;
        }

        return implode(':', str_split($hex, 2));
    }

    /**
     * Formats an already-normalized colon-separated MAC (as stored/matched
     * everywhere else) into Huawei's own display style, e.g.
     * "20:3a:43:16:6c:90" -> "203a-4316-6c90" - for display only, never used
     * for matching/storage so the canonical colon form stays the single
     * source of truth.
     */
    public static function toHuawei(string $normalized): string
    {
        $hex = str_replace(':', '', $normalized);

        return implode('-', str_split($hex, 4));
    }
}
