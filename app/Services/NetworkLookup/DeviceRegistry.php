<?php

namespace App\Services\NetworkLookup;

/**
 * Reads/writes storage/app/private/network-lookup/devices.json - the same
 * hand-maintained file network-lookup:sync-devices bulk-imports from. The
 * web UI (NetworkDeviceController) writes through this on every add/edit/
 * remove so the file stays a true mirror of the DB, not just a one-time
 * import source: that's what makes it possible to bootstrap a new
 * environment (e.g. production) by copying this one file and running
 * network-lookup:sync-devices once, instead of re-entering every device by
 * hand - and once the UI is live somewhere, editing devices there keeps
 * that environment's own copy of the file current too.
 */
class DeviceRegistry
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function load(): array
    {
        $path = config('network-lookup.devices_file');

        if (! is_file($path)) {
            return [];
        }

        $entries = json_decode(file_get_contents($path), true);

        return is_array($entries) ? $entries : [];
    }

    public function add(array $device): void
    {
        $entries = $this->load();
        $entries[] = $this->toEntry($device);
        $this->save($entries);
    }

    /**
     * Replaces the entry matching $oldName (the device's name before this
     * edit, in case it was renamed) - appends instead if no entry with that
     * name was found, so editing a device added before this file existed
     * doesn't silently fail to persist.
     */
    public function replace(string $oldName, array $device): void
    {
        $entries = $this->load();
        $found = false;

        foreach ($entries as &$entry) {
            if (($entry['name'] ?? null) === $oldName) {
                $entry = $this->toEntry($device);
                $found = true;

                break;
            }
        }
        unset($entry);

        if (! $found) {
            $entries[] = $this->toEntry($device);
        }

        $this->save($entries);
    }

    /**
     * Shared validation for anything that reads raw entries from a
     * devices.json-shaped array (network-lookup:sync-devices, the web UI's
     * mass-import upload) - one place for the rules so they can't drift
     * between the two paths. Returns the entry with defaults applied
     * (protocol defaults to 'ssh'), or null if it's invalid.
     *
     * @return ?array{name: string, ip: string, vendor: string, role: string, protocol: string}
     */
    public function validateEntry(array $entry): ?array
    {
        $name = $entry['name'] ?? null;
        $ip = $entry['ip'] ?? null;
        $vendor = $entry['vendor'] ?? null;
        $role = $entry['role'] ?? null;
        $protocol = $entry['protocol'] ?? 'ssh';

        if (
            ! is_string($name) || $name === ''
            || ! filter_var($ip, FILTER_VALIDATE_IP)
            || ! in_array($vendor, ['huawei', 'cisco'], true)
            || ! in_array($role, ['l2', 'core'], true)
            || ! in_array($protocol, ['ssh', 'telnet'], true)
        ) {
            return null;
        }

        return compact('name', 'ip', 'vendor', 'role', 'protocol');
    }

    public function remove(string $name): void
    {
        $entries = array_values(array_filter(
            $this->load(),
            fn (array $entry) => ($entry['name'] ?? null) !== $name
        ));

        $this->save($entries);
    }

    private function toEntry(array $device): array
    {
        return [
            'name' => $device['name'],
            'ip' => $device['mgmt_ip'],
            'vendor' => $device['vendor'],
            'role' => $device['role'],
            'protocol' => $device['protocol'],
        ];
    }

    private function save(array $entries): void
    {
        $path = config('network-lookup.devices_file');
        @mkdir(dirname($path), 0777, true);

        file_put_contents($path, json_encode(array_values($entries), JSON_PRETTY_PRINT).PHP_EOL);
    }
}
