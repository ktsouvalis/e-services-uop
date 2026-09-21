<?php

namespace App\Services\NetworkLookup\Parsers;

use App\Services\NetworkLookup\MacAddressNormalizer;

/**
 * Parses Huawei VRP `display arp` output (run only against the core device,
 * KEDD_Central_S6730) into [ip, mac, vlan] rows.
 *
 * NOT YET VALIDATED AGAINST REAL DEVICE OUTPUT - built from the well-known
 * VRP table shape (IP ADDRESS / MAC ADDRESS / EXPIRE(M) / TYPE/VLAN /
 * INTERFACE columns), per the implementation plan's step 1: capture real
 * `display arp` output from the live core switch and adjust this
 * regex/column-split logic against it before relying on it.
 */
class HuaweiArpParser
{
    /**
     * @return array<int, array{ip: string, mac: string, vlan: ?string}>
     */
    public function parse(string $output): array
    {
        $rows = [];

        foreach (explode("\n", $output) as $line) {
            $line = trim($line);

            // 10.23.14.156   183d-2da2-fd87  20   D-0/2313   Vlanif2313
            if (! preg_match(
                '/^(\d{1,3}(?:\.\d{1,3}){3})\s+([0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4})\s+\S+\s+(\S+)/',
                $line,
                $m
            )) {
                continue;
            }

            $mac = MacAddressNormalizer::normalize($m[2]);
            if ($mac === null) {
                continue;
            }

            // The TYPE/VLAN column looks like "D-0/2313" (dynamic, vlan
            // 2313) or "I-0/-" (static/none) - take the trailing numeric
            // segment after the last '/' as the vlan, if any.
            $vlan = null;
            if (preg_match('#/(\d+)$#', $m[3], $vm)) {
                $vlan = $vm[1];
            }

            $rows[] = [
                'ip' => $m[1],
                'mac' => $mac,
                'vlan' => $vlan,
            ];
        }

        return $rows;
    }
}
