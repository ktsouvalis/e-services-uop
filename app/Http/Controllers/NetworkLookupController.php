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
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Ods;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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
     * Exports the same mac/arp history a search on the index page shows,
     * as a spreadsheet - xlsx or ods, picked via ?format. Re-runs search()
     * rather than reading from the request's view data, since this is a
     * separate GET (a link, not a form re-post).
     */
    public function export(Request $request)
    {
        $query = trim((string) $request->query('q', ''));
        $format = $request->query('format', 'xlsx');

        if ($query === '' || ! in_array($format, ['xlsx', 'ods'], true)) {
            abort(404);
        }

        [$result, $macHistory, $arpHistory, $error] = $this->search($query);

        if ($error !== null) {
            return back()->with('error', $error);
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Lookup');

        $row = 1;
        $sheet->setCellValue("A{$row}", 'Query');
        $sheet->setCellValue("B{$row}", $query);
        $row += 2;

        if ($result !== null) {
            foreach ([
                'IP' => $result['ip_address'] ?? '—',
                'MAC' => MacAddressNormalizer::toHuawei($result['mac_address']),
                'Switch' => $result['device']->name,
                'Port' => $result['port'],
                'Port description' => $result['port_description'] ?? '—',
                'VLAN' => $result['vlan'] ?? '—',
            ] as $label => $value) {
                $sheet->setCellValue("A{$row}", $label);
                $sheet->setCellValue("B{$row}", $value);
                $row++;
            }
            $row++;
        }

        if ($macHistory->isNotEmpty()) {
            $sheet->fromArray(['Switch', 'Port', 'VLAN', 'First seen', 'Last seen'], null, "A{$row}");
            $row++;

            foreach ($macHistory as $historyRow) {
                $sheet->fromArray([
                    $historyRow->device->name ?? '—',
                    $historyRow->port,
                    $historyRow->vlan ?? '—',
                    $historyRow->first_seen_at->format('Y-m-d H:i:s'),
                    $historyRow->last_seen_at->format('Y-m-d H:i:s'),
                ], null, "A{$row}");
                $row++;
            }
            $row++;
        }

        if ($arpHistory->isNotEmpty()) {
            $sheet->fromArray(['IP', 'VLAN', 'First seen', 'Last seen'], null, "A{$row}");
            $row++;

            foreach ($arpHistory as $historyRow) {
                $sheet->fromArray([
                    $historyRow->ip_address,
                    $historyRow->vlan ?? '—',
                    $historyRow->first_seen_at->format('Y-m-d H:i:s'),
                    $historyRow->last_seen_at->format('Y-m-d H:i:s'),
                ], null, "A{$row}");
                $row++;
            }
        }

        $writer = $format === 'ods' ? new Ods($spreadsheet) : new Xlsx($spreadsheet);
        $mimeType = $format === 'ods'
            ? 'application/vnd.oasis.opendocument.spreadsheet'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        $safeQuery = preg_replace('/[^A-Za-z0-9._-]/', '-', $query);
        $filename = "network-lookup-{$safeQuery}.{$format}";
        $path = storage_path('app/private/network-lookup/exports/'.$filename);
        @mkdir(dirname($path), 0777, true);
        $writer->save($path);

        return response()->download($path, $filename, ['Content-Type' => $mimeType])->deleteFileAfterSend();
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
            'mac_address_display' => MacAddressNormalizer::toHuawei($currentMac),
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
