<?php

namespace App\Console\Commands;

use App\Models\NetworkDevice;
use App\Services\NetworkLookup\DeviceRegistry;
use Illuminate\Console\Command;

/**
 * Reads the hand-maintained device list at config('network-lookup.devices_file')
 * and upserts NetworkDevice rows by name - this is the primary way to add/
 * rename/re-IP a device, replacing the earlier config-repo-parsing approach
 * (kept separately as network-lookup:import-port-descriptions, since that
 * data genuinely only exists in the running-configs). Never deletes a device
 * missing from the file, and never touches `enabled` on an existing row, so
 * a manual on/off toggle in the UI survives a re-sync.
 */
class SyncNetworkDevices extends Command
{
    protected $signature = 'network-lookup:sync-devices';

    protected $description = 'Sync the network device inventory from the hand-maintained devices.json file';

    public function handle(DeviceRegistry $registry): int
    {
        $path = config('network-lookup.devices_file');

        if (! is_file($path)) {
            $this->error("Devices file not found: {$path}");

            return self::FAILURE;
        }

        $entries = $registry->load();
        $synced = 0;
        $skipped = 0;

        foreach ($entries as $entry) {
            $validated = $registry->validateEntry($entry);

            if ($validated === null) {
                $this->warn('Skipping invalid entry: '.json_encode($entry));
                $skipped++;

                continue;
            }

            $device = NetworkDevice::updateOrCreate(
                ['name' => $validated['name']],
                ['mgmt_ip' => $validated['ip'], 'vendor' => $validated['vendor'], 'role' => $validated['role'], 'protocol' => $validated['protocol']]
            );

            $registry->syncTrunkPorts($device, $validated['trunk_ports'] ?? null);

            $synced++;
        }

        $this->info("Synced {$synced} device(s), skipped {$skipped}.");

        return self::SUCCESS;
    }
}
