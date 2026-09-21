<?php

namespace App\Http\Controllers;

use App\Jobs\NetworkLookup\PollAllDevices;
use App\Models\NetworkArpHistory;
use App\Models\NetworkDevice;
use App\Models\NetworkDevicePort;
use App\Models\NetworkMacHistory;
use App\Models\NetworkPollRun;
use App\Services\NetworkLookup\MacAddressNormalizer;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NetworkLookupController extends Controller
{
    public function index(Request $request)
    {
        $query = trim((string) $request->query('q', ''));
        $result = null;
        $macHistory = collect();
        $arpHistory = collect();
        $error = null;

        if ($query !== '') {
            [$result, $macHistory, $arpHistory, $error] = $this->search($query);
        }

        $devices = NetworkDevice::orderBy('role')->orderBy('name')->get();
        $latestRun = NetworkPollRun::latest('id')->first();

        return view('network-lookup.index', [
            'query' => $query,
            'result' => $result,
            'macHistory' => $macHistory,
            'arpHistory' => $arpHistory,
            'searchError' => $error,
            'devices' => $devices,
            'latestRun' => $latestRun,
            'latestRunReportedCount' => $latestRun?->reportedCount(),
        ]);
    }

    public function poll()
    {
        PollAllDevices::dispatch();

        return redirect()->route('network-lookup.index')->with('success', 'Poll queued for all enabled devices.');
    }

    /**
     * @return array{0: ?array, 1: \Illuminate\Support\Collection, 2: \Illuminate\Support\Collection, 3: ?string}
     */
    private function search(string $query): array
    {
        $isIp = filter_var($query, FILTER_VALIDATE_IP) !== false;
        $mac = MacAddressNormalizer::normalize($query);

        if (! $isIp && $mac === null) {
            return [null, collect(), collect(), 'Enter a valid IP address or MAC address.'];
        }

        $arpHistory = collect();
        $currentMac = $mac;

        if ($isIp) {
            $arpHistory = NetworkArpHistory::where('ip_address', $query)->orderByDesc('last_seen_at')->get();
            $currentMac = $arpHistory->first()?->mac_address;
        }

        $macHistory = collect();
        if ($currentMac !== null) {
            $macHistory = $this->macHistoryExcludingTrunkPorts($currentMac)->get();
        }

        if (! $isIp && $currentMac !== null) {
            // Searched by MAC directly - also show any IP(s) it has resolved to.
            $arpHistory = NetworkArpHistory::where('mac_address', $currentMac)->orderByDesc('last_seen_at')->get();
        }

        $currentLocation = $macHistory->first();

        if ($currentMac === null || $currentLocation === null) {
            return [null, $macHistory, $arpHistory, null];
        }

        $portDescription = NetworkDevicePort::where('network_device_id', $currentLocation->network_device_id)
            ->where('port', $currentLocation->port)
            ->value('description');

        $result = [
            'ip_address' => $isIp ? $query : $arpHistory->first()?->ip_address,
            'mac_address' => $currentMac,
            'device' => $currentLocation->device,
            'port' => $currentLocation->port,
            'port_description' => $portDescription,
            'vlan' => $currentLocation->vlan,
            'last_seen_at' => $currentLocation->last_seen_at,
        ];

        return [$result, $macHistory, $arpHistory, null];
    }

    /**
     * A MAC learned on a trunk is transit traffic, not that device's actual
     * location - PollSwitchMacTable stops *new* trunk-port rows from being
     * written, but that alone doesn't hide history rows recorded before a
     * port's link_type was known (or before this exclusion existed), so the
     * search itself also has to exclude any row whose (device, port) is
     * currently known to be a trunk, or a stale trunk row can still win as
     * "most recent" for a MAC that hasn't been freshly polled since.
     */
    private function macHistoryExcludingTrunkPorts(string $mac)
    {
        return NetworkMacHistory::with('device')
            ->where('mac_address', $mac)
            ->whereNotExists(function (Builder $query) {
                $query->select(DB::raw(1))
                    ->from('network_device_ports')
                    ->whereColumn('network_device_ports.network_device_id', 'network_mac_histories.network_device_id')
                    ->whereColumn('network_device_ports.port', 'network_mac_histories.port')
                    ->where('network_device_ports.link_type', 'trunk');
            })
            ->orderByDesc('last_seen_at');
    }
}
