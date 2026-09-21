<?php

namespace App\Http\Controllers;

use App\Models\NetworkDevice;
use App\Services\NetworkLookup\DeviceRegistry;
use Illuminate\Http\Request;

/**
 * Device inventory CRUD - a web UI on top of the same
 * storage/app/private/network-lookup/devices.json file
 * network-lookup:sync-devices bulk-imports from (via DeviceRegistry, which
 * every add/edit/remove here writes through), so the file stays the
 * portable source of truth for bootstrapping another environment, not just
 * a one-time import snapshot. No Policy/Gate::authorize here, same as the
 * rest of this module - NetworkLookupEnabled (menu-gated, any authenticated
 * user) covers the whole /network-lookup route group these routes live
 * under.
 */
class NetworkDeviceController extends Controller
{
    public function __construct(private readonly DeviceRegistry $registry)
    {
    }

    public function create()
    {
        return view('network-lookup.devices.create');
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);
        $trunkPorts = $validated['trunk_ports'] ?? null;
        $deviceFields = collect($validated)->except('trunk_ports')->all();

        $device = NetworkDevice::create($deviceFields);
        $this->registry->add($deviceFields + ['trunk_ports' => $trunkPorts]);
        $this->registry->syncTrunkPorts($device, $trunkPorts);

        return redirect()->route('network-lookup.index')->with('success', 'Device added.');
    }

    public function edit(NetworkDevice $device)
    {
        $trunkPorts = $this->registry->trunkPortsCsv($device);

        return view('network-lookup.devices.edit', compact('device', 'trunkPorts'));
    }

    public function update(Request $request, NetworkDevice $device)
    {
        $validated = $this->validated($request, $device);
        $trunkPorts = $validated['trunk_ports'] ?? null;
        $deviceFields = collect($validated)->except('trunk_ports')->all();
        $oldName = $device->name;

        $device->update($deviceFields);
        $this->registry->replace($oldName, $deviceFields + ['trunk_ports' => $trunkPorts]);
        $this->registry->syncTrunkPorts($device, $trunkPorts);

        return redirect()->route('network-lookup.index')->with('success', 'Device updated.');
    }

    public function destroy(NetworkDevice $device)
    {
        $this->registry->remove($device->name);
        $device->delete();

        return redirect()->route('network-lookup.index')->with('success', 'Device removed.');
    }

    public function showImport()
    {
        return view('network-lookup.devices.import');
    }

    /**
     * Mass-import: upload a devices.json-shaped file (the same format
     * network-lookup:sync-devices reads) and merge every valid entry into
     * both the DB and the canonical devices.json - add if new, update in
     * place if a device with that name already exists (never touches
     * `enabled`, same as sync-devices). Uses the same DeviceRegistry::
     * validateEntry() rules as that command, so a file that works with one
     * works with the other.
     */
    public function import(Request $request)
    {
        $request->validate(['file' => ['required', 'file']]);

        $entries = json_decode(file_get_contents($request->file('file')->getRealPath()), true);

        if (! is_array($entries)) {
            return back()->with('error', 'That file is not valid JSON (expected an array of device entries).');
        }

        $imported = 0;
        $skipped = [];

        foreach ($entries as $entry) {
            $entry = is_array($entry) ? $entry : [];
            $validated = $this->registry->validateEntry($entry);

            if ($validated === null) {
                $skipped[] = $entry['name'] ?? json_encode($entry);

                continue;
            }

            $fields = [
                'name' => $validated['name'],
                'mgmt_ip' => $validated['ip'],
                'vendor' => $validated['vendor'],
                'role' => $validated['role'],
                'protocol' => $validated['protocol'],
            ];

            $device = NetworkDevice::updateOrCreate(['name' => $fields['name']], [
                'mgmt_ip' => $fields['mgmt_ip'],
                'vendor' => $fields['vendor'],
                'role' => $fields['role'],
                'protocol' => $fields['protocol'],
            ]);
            $this->registry->replace($fields['name'], $fields + ['trunk_ports' => $validated['trunk_ports']]);
            $this->registry->syncTrunkPorts($device, $validated['trunk_ports']);

            $imported++;
        }

        $message = "Imported {$imported} device(s).";
        if ($skipped !== []) {
            $message .= ' Skipped '.count($skipped).': '.implode(', ', $skipped);
        }

        return redirect()->route('network-lookup.index')->with($skipped === [] ? 'success' : 'error', $message);
    }

    public function toggleEnabled(Request $request, NetworkDevice $device)
    {
        $device->enabled = (bool) $request->input('enabled');
        $device->save();

        return response()->json(['success' => true]);
    }

    private function validated(Request $request, ?NetworkDevice $device = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:network_devices,name,'.($device?->id)],
            'mgmt_ip' => ['required', 'ip'],
            'vendor' => ['required', 'in:huawei,cisco'],
            'role' => ['required', 'in:l2,core'],
            'protocol' => ['required', 'in:ssh,telnet'],
            'trunk_ports' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
