<?php

namespace App\Services\NetworkLookup\Parsers;

use App\Services\NetworkLookup\MacAddressNormalizer;

/**
 * Parses Huawei VRP `display mac-address` output into [mac, port, vlan] rows.
 *
 * NOT YET VALIDATED AGAINST REAL DEVICE OUTPUT - built from the well-known
 * VRP table shape (MAC Address / VLAN(/VSI/BD) / Learned-From / Type columns),
 * per the implementation plan's step 1: capture real output from a live
 * Huawei switch and adjust this regex/column-split logic against it before
 * relying on it. Column widths and headers can vary by firmware (V200R024
 * here per the configs, but still device/model dependent).
 */
class HuaweiMacTableParser
{
    /**
     * @return array<int, array{mac: string, port: string, vlan: ?string}>
     */
    public function parse(string $output): array
    {
        $rows = [];

        foreach (explode("\n", $output) as $line) {
            $line = trim($line);

            // A MAC in Huawei's dash-grouped form: xxxx-xxxx-xxxx
            if (! preg_match('/^([0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4})\s+(\S+)\s+(\S+)/', $line, $m)) {
                continue;
            }

            $mac = MacAddressNormalizer::normalize($m[1]);
            if ($mac === null) {
                continue;
            }

            // Second column is typically "VLAN/VSI/BD" (e.g. "2313/-/-") or
            // just a bare VLAN id - take the leading numeric segment.
            $vlan = null;
            if (preg_match('/^(\d+)/', $m[2], $vm)) {
                $vlan = $vm[1];
            }

            $rows[] = [
                'mac' => $mac,
                'port' => $m[3],
                'vlan' => $vlan,
            ];
        }

        return $rows;
    }
}
