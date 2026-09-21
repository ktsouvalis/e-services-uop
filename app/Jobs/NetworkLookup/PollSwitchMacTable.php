<?php

namespace App\Jobs\NetworkLookup;

use App\Models\NetworkDevice;
use App\Models\NetworkDevicePort;
use App\Models\NetworkMacHistory;
use App\Services\NetworkLookup\Parsers\CiscoMacTableParser;
use App\Services\NetworkLookup\Parsers\HuaweiMacTableParser;
use App\Services\NetworkLookup\SshCommandRunner;
use App\Services\NetworkLookup\TelnetCommandRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * One job per L2 device (fan-out, not a loop over all switches in one job) -
 * at ~49 switches over synchronous SSH, a single job looping through all of
 * them the way Pangolin/Authentik's PollCluster loops 3 HTTP nodes would blow
 * past any reasonable $timeout. Deliberately NOT using IsMonitored - see
 * PollAllDevices for why (would flood /jobs every poll cycle x 49 devices).
 */
class PollSwitchMacTable implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;

    public function __construct(private readonly NetworkDevice $device, private readonly ?int $pollRunId = null)
    {
    }

    public function handle(
        SshCommandRunner $sshRunner,
        TelnetCommandRunner $telnetRunner,
        HuaweiMacTableParser $huaweiParser,
        CiscoMacTableParser $ciscoParser,
    ): void {
        $commands = match ($this->device->vendor) {
            'huawei' => ['screen-length 0 temporary', 'display mac-address'],
            'cisco' => ['terminal length 0', 'show mac address-table'],
            default => null,
        };

        if ($commands === null) {
            $this->markPolled('error', "Unknown vendor: {$this->device->vendor}");

            return;
        }

        $runner = $this->device->protocol === 'telnet' ? $telnetRunner : $sshRunner;

        try {
            $output = $runner->run($this->device->mgmt_ip, $commands);
        } catch (Throwable $e) {
            $this->markPolled('error', $e->getMessage());

            return;
        }

        $rows = $this->device->vendor === 'huawei'
            ? $huaweiParser->parse($output)
            : $ciscoParser->parse($output);

        $trunkPorts = NetworkDevicePort::where('network_device_id', $this->device->id)
            ->where('link_type', 'trunk')
            ->pluck('port')
            ->all();

        foreach ($rows as $row) {
            // A MAC learned on a trunk is just transit traffic to/from
            // another switch, not where that device is physically plugged
            // in - excluded so history only ever reflects the final,
            // physical access port.
            if (in_array($row['port'], $trunkPorts, true)) {
                continue;
            }

            $this->upsertHistory($row['mac'], $row['port'], $row['vlan']);
        }

        $this->markPolled('ok', null);
    }

    private function upsertHistory(string $mac, string $port, ?string $vlan): void
    {
        $existing = NetworkMacHistory::where('mac_address', $mac)
            ->orderByDesc('last_seen_at')
            ->first();

        if (
            $existing
            && $existing->network_device_id === $this->device->id
            && $existing->port === $port
            && $existing->vlan === $vlan
        ) {
            $existing->update(['last_seen_at' => now()]);

            return;
        }

        NetworkMacHistory::create([
            'network_device_id' => $this->device->id,
            'mac_address' => $mac,
            'port' => $port,
            'vlan' => $vlan,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
    }

    private function markPolled(string $status, ?string $error): void
    {
        $this->device->update([
            'last_polled_at' => now(),
            'last_poll_status' => $status,
            'last_poll_error' => $error,
            'last_poll_run_id' => $this->pollRunId,
        ]);
    }
}
