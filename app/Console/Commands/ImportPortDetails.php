<?php

namespace App\Console\Commands;

use App\Models\NetworkDevice;
use App\Models\NetworkDevicePort;
use App\Services\NetworkLookup\DeviceConfigParser;
use Illuminate\Console\Command;

/**
 * Optional, occasional enrichment - port descriptions ("esda_lab_link" etc.)
 * and link-type (trunk/access/hybrid) only exist in the running-configs, not
 * in any live command output, so this is the one place the app still reads
 * a config repo checkout. It's separate from device identity (name/ip/
 * vendor), which comes from network-lookup:sync-devices instead - a device
 * must already exist (matched by name) for its ports to be imported here.
 * link_type is what lets PollSwitchMacTable exclude trunk-learned MAC
 * entries from history (a MAC seen on a trunk isn't where that device is
 * physically plugged in - it's just transit traffic to/from another switch).
 */
class ImportPortDetails extends Command
{
    protected $signature = 'network-lookup:import-port-details {path : Directory containing the *.ios config files}';

    protected $description = 'Import per-port descriptions and link-type (trunk/access/hybrid) from a local checkout of the switch config repo, for devices already in the inventory';

    public function handle(DeviceConfigParser $parser): int
    {
        $path = rtrim($this->argument('path'), '/');

        if (! is_dir($path)) {
            $this->error("Not a directory: {$path}");

            return self::FAILURE;
        }

        $files = glob("{$path}/*.ios");

        if (empty($files)) {
            $this->error("No *.ios files found in {$path}");

            return self::FAILURE;
        }

        $imported = 0;
        $skipped = 0;

        foreach ($files as $file) {
            $text = file_get_contents($file);
            $name = $parser->extractName($text);

            $device = $name !== null ? NetworkDevice::where('name', $name)->first() : null;

            if ($device === null) {
                $this->warn('Skipping '.basename($file)." (no matching device named {$name} - run network-lookup:sync-devices first)");
                $skipped++;

                continue;
            }

            foreach ($parser->extractPorts($text, $device->vendor) as $port => $details) {
                NetworkDevicePort::updateOrCreate(
                    ['network_device_id' => $device->id, 'port' => $port],
                    ['description' => $details['description'], 'link_type' => $details['link_type']]
                );
            }

            $imported++;
        }

        $this->info("Imported port details for {$imported} device(s), skipped {$skipped}.");

        return self::SUCCESS;
    }
}
