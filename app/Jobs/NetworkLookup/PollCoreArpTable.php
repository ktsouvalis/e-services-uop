<?php

namespace App\Jobs\NetworkLookup;

use App\Models\NetworkArpHistory;
use App\Models\NetworkDevice;
use App\Services\NetworkLookup\Parsers\HuaweiArpParser;
use App\Services\NetworkLookup\SshCommandRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * SSHes into the single core device (role = 'core', see
 * config('network-lookup.core_device_name')) and runs `display arp` - that
 * device is Huawei-only per the current network (KEDD_Central_S6730), so
 * this doesn't need a Cisco ARP parser.
 */
class PollCoreArpTable implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;

    public function __construct(private readonly NetworkDevice $device, private readonly ?int $pollRunId = null)
    {
    }

    public function handle(SshCommandRunner $runner, HuaweiArpParser $parser): void
    {
        try {
            $output = $runner->run($this->device->mgmt_ip, ['screen-length 0 temporary', 'display arp']);
        } catch (Throwable $e) {
            $this->markPolled('error', $e->getMessage());

            return;
        }

        foreach ($parser->parse($output) as $row) {
            $this->upsertHistory($row['ip'], $row['mac'], $row['vlan']);
        }

        $this->markPolled('ok', null);
    }

    private function upsertHistory(string $ip, string $mac, ?string $vlan): void
    {
        $existing = NetworkArpHistory::where('ip_address', $ip)
            ->orderByDesc('last_seen_at')
            ->first();

        if ($existing && $existing->mac_address === $mac && $existing->vlan === $vlan) {
            $existing->update(['last_seen_at' => now()]);

            return;
        }

        NetworkArpHistory::create([
            'ip_address' => $ip,
            'mac_address' => $mac,
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
