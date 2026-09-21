<?php

namespace App\Services\NetworkLookup\Parsers;

use App\Services\NetworkLookup\MacAddressNormalizer;

/**
 * Parses Cisco IOS `show mac address-table` output into [mac, port, vlan]
 * rows.
 *
 * NOT YET VALIDATED AGAINST REAL DEVICE OUTPUT - built from the well-known
 * IOS table shape (Vlan / Mac Address / Type / Ports columns), per the
 * implementation plan's step 1: capture real output from a live Cisco switch
 * and adjust this regex/column-split logic against it before relying on it.
 */
class CiscoMacTableParser
{
    /**
     * @return array<int, array{mac: string, port: string, vlan: ?string}>
     */
    public function parse(string $output): array
    {
        $rows = [];

        foreach (explode("\n", $output) as $line) {
            $line = trim($line);

            // Vlan   Mac Address       Type        Ports
            //  2327  0018.0a1b.2c3d    DYNAMIC     Fa0/1
            if (! preg_match(
                '/^(\d+)\s+([0-9a-fA-F]{4}\.[0-9a-fA-F]{4}\.[0-9a-fA-F]{4})\s+\S+\s+(\S+)/',
                $line,
                $m
            )) {
                continue;
            }

            $mac = MacAddressNormalizer::normalize($m[2]);
            if ($mac === null) {
                continue;
            }

            $rows[] = [
                'mac' => $mac,
                'port' => $m[3],
                'vlan' => $m[1],
            ];
        }

        return $rows;
    }
}
