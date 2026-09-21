<?php

namespace App\Services\NetworkLookup;

/**
 * Parses a single switch's running-config text (Huawei VRP or Cisco IOS) for
 * the static facts derivable from config alone: vendor, device name, and
 * per-port descriptions/link-type. Used by the
 * network-lookup:import-port-details command only - device identity
 * (name/ip/vendor) comes from the hand-maintained devices.json instead (see
 * network-lookup:sync-devices). Never used at poll time, since MAC/ARP
 * tables are dynamic data configs don't contain.
 */
class DeviceConfigParser
{
    public function detectVendor(string $text): ?string
    {
        if (preg_match('/^sysname\s+/m', $text)) {
            return 'huawei';
        }

        if (preg_match('/^hostname\s+/m', $text)) {
            return 'cisco';
        }

        return null;
    }

    public function extractName(string $text): ?string
    {
        if (preg_match('/^sysname\s+(\S+)/m', $text, $m)) {
            return $m[1];
        }

        if (preg_match('/^hostname\s+(\S+)/m', $text, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * @return array<string, string> port name => description
     */
    public function extractPortDescriptions(string $text): array
    {
        return array_filter(array_map(
            fn (array $details) => $details['description'],
            $this->extractPortDetails($text),
        ));
    }

    /**
     * @return array<string, string> port name => 'trunk' | 'access' | 'hybrid'
     */
    public function extractPortLinkTypes(string $text): array
    {
        return array_filter(array_map(
            fn (array $details) => $details['link_type'],
            $this->extractPortDetails($text),
        ));
    }

    /**
     * Every interface block found, with its port name normalized to the
     * abbreviated form the switch's own `display mac-address` / `show mac
     * address-table` output uses (e.g. config's "XGigabitEthernet0/0/1"
     * becomes "XGE0/0/1") - confirmed live that without this, a trunk port
     * imported under its full config name never matches the abbreviated
     * name PollSwitchMacTable sees at poll time, so the trunk exclusion
     * silently never fires for it. Unlike extractPortDescriptions/
     * extractPortLinkTypes above (which drop a port entirely if it has
     * neither field set), this returns every interface block so a
     * NetworkDevicePort row exists even for a port with no description/
     * link-type yet.
     *
     * @return array<string, array{description: ?string, link_type: ?string}>
     */
    public function extractPorts(string $text, string $vendor): array
    {
        $normalized = [];

        foreach ($this->extractPortDetails($text) as $port => $details) {
            $normalized[$this->normalizePortName($port, $vendor)] = $details;
        }

        return $normalized;
    }

    private const HUAWEI_INTERFACE_ABBREVIATIONS = [
        'XGigabitEthernet' => 'XGE',
        'GigabitEthernet' => 'GE',
        'FastEthernet' => 'FE',
    ];

    private const CISCO_INTERFACE_ABBREVIATIONS = [
        'TenGigabitEthernet' => 'Te',
        'GigabitEthernet' => 'Gi',
        'FastEthernet' => 'Fa',
    ];

    private function normalizePortName(string $port, string $vendor): string
    {
        $abbreviations = $vendor === 'cisco' ? self::CISCO_INTERFACE_ABBREVIATIONS : self::HUAWEI_INTERFACE_ABBREVIATIONS;

        foreach ($abbreviations as $full => $abbreviation) {
            if (str_starts_with($port, $full)) {
                return $abbreviation.substr($port, strlen($full));
            }
        }

        // Eth-Trunk, MEth, etc. - already in their final, un-abbreviated form.
        return $port;
    }

    /**
     * @return array<string, array{description: ?string, link_type: ?string}>
     */
    private function extractPortDetails(string $text): array
    {
        $ports = [];
        $currentPort = null;

        foreach (explode("\n", $text) as $line) {
            if (preg_match('/^interface\s+(\S+)/', $line, $m)) {
                $currentPort = $m[1];
                $ports[$currentPort] ??= ['description' => null, 'link_type' => null];

                continue;
            }

            if ($currentPort === null) {
                continue;
            }

            if ($this->isBlockBoundary($line)) {
                $currentPort = null;

                continue;
            }

            if ($ports[$currentPort]['description'] === null && preg_match('/^\s*description\s+(.+)$/', $line, $m)) {
                $ports[$currentPort]['description'] = trim($m[1]);
            }

            // Huawei: "port link-type trunk|access|hybrid".
            // Cisco: "switchport mode trunk|access".
            if (
                $ports[$currentPort]['link_type'] === null
                && preg_match('/^\s*(?:port link-type|switchport mode)\s+(trunk|access|hybrid)\s*$/', $line, $m)
            ) {
                $ports[$currentPort]['link_type'] = $m[1];
            }
        }

        return $ports;
    }

    /**
     * Huawei separates top-level blocks with a bare "#" line, Cisco with "!" -
     * either one, or any other unindented non-blank line, ends the current
     * interface/VLAN sub-block.
     */
    private function isBlockBoundary(string $line): bool
    {
        if ($line === '#' || $line === '!') {
            return true;
        }

        return trim($line) !== '' && $line[0] !== ' ';
    }
}
