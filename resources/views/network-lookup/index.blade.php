@push('title')
    <title>Network Lookup</title>
@endpush
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.body.addEventListener('change', function(e) {
        if (e.target && e.target.classList.contains('toggle-enabled')) {
            const toggle = e.target;
            const deviceId = toggle.dataset.id;
            const enabled = toggle.checked ? 1 : 0;
            const url = "{{ url('network-lookup/devices') }}/" + deviceId + "/toggle-enabled";
            fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ enabled })
            })
            .then(async response => {
                let data = {};
                try {
                    data = await response.json();
                } catch (e) {
                    await response.text();
                }
                return data;
            })
            .then(data => {
                if (data.success) {
                    toggle.nextElementSibling.textContent = enabled ? 'On' : 'Off';
                } else {
                    alert('Failed to update status');
                    toggle.checked = !enabled;
                }
            })
            .catch(() => {
                alert('Failed to update status');
                toggle.checked = !enabled;
            });
        }
    });

    document.getElementById('devices-select-all')?.addEventListener('click', function() {
        document.querySelectorAll('.toggle-enabled').forEach(function(toggle) {
            if (!toggle.checked) {
                toggle.checked = true;
                toggle.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    });

    document.getElementById('devices-select-none')?.addEventListener('click', function() {
        document.querySelectorAll('.toggle-enabled').forEach(function(toggle) {
            if (toggle.checked) {
                toggle.checked = false;
                toggle.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    });

    document.getElementById('devices-invert-selection')?.addEventListener('click', function() {
        document.querySelectorAll('.toggle-enabled').forEach(function(toggle) {
            toggle.checked = !toggle.checked;
            toggle.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });
});
</script>
@endpush
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Network Lookup') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">{{ __('Search by IP or MAC address') }}</h3>
                <form action="{{ route('network-lookup.index') }}" method="GET" class="flex items-end gap-3">
                    <div class="flex-1">
                        <label for="q" class="block text-sm font-medium text-gray-700">{{ __('IP or MAC address') }}</label>
                        <input type="text" name="q" id="q" value="{{ $query }}" placeholder="10.23.14.156 or aa:bb:cc:dd:ee:ff"
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                    </div>
                    <x-primary-button>{{ __('Search') }}</x-primary-button>
                </form>

                @if ($searchError)
                    <p class="text-red-600 text-sm mt-4">{{ $searchError }}</p>
                @endif

                @if ($query !== '' && ! $searchError)
                    @if ($result)
                        <div class="mt-6 border border-gray-300 rounded-md p-4 bg-gray-50">
                            <h4 class="font-semibold mb-2">{{ __('Current location') }}</h4>
                            <dl class="grid grid-cols-2 md:grid-cols-3 gap-x-4 gap-y-2 text-sm">
                                <div><dt class="text-gray-500">IP</dt><dd>{{ $result['ip_address'] ?? '—' }}</dd></div>
                                <div><dt class="text-gray-500">MAC</dt><dd>{{ $result['mac_address'] }}</dd></div>
                                <div><dt class="text-gray-500">Switch</dt><dd>{{ $result['device']->name }} ({{ $result['device']->mgmt_ip }})</dd></div>
                                <div><dt class="text-gray-500">Port</dt><dd>{{ $result['port'] }}</dd></div>
                                <div><dt class="text-gray-500">Port description</dt><dd>{{ $result['port_description'] ?? '—' }}</dd></div>
                                <div><dt class="text-gray-500">VLAN</dt><dd>{{ $result['vlan'] ?? '—' }}</dd></div>
                                <div><dt class="text-gray-500">Last confirmed</dt><dd>{{ $result['last_seen_at']->diffForHumans() }}</dd></div>
                            </dl>
                        </div>
                    @else
                        <p class="text-gray-600 text-sm mt-4">{{ __('No match found for :q', ['q' => $query]) }}</p>
                    @endif

                    @if ($macHistory->isNotEmpty() || $arpHistory->isNotEmpty())
                        <details class="mt-6">
                            <summary class="cursor-pointer font-semibold text-sm text-gray-700">{{ __('History') }}</summary>

                            @if ($macHistory->isNotEmpty())
                                <table class="my-3 w-full table-auto divide-y divide-gray-200 border border-gray-300 text-sm">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-2 text-left border border-gray-300">Switch</th>
                                            <th class="px-4 py-2 text-left border border-gray-300">Port</th>
                                            <th class="px-4 py-2 text-left border border-gray-300">VLAN</th>
                                            <th class="px-4 py-2 text-left border border-gray-300">First seen</th>
                                            <th class="px-4 py-2 text-left border border-gray-300">Last seen</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach ($macHistory as $row)
                                            <tr>
                                                <td class="px-4 py-2 border border-gray-300">{{ $row->device->name ?? '—' }}</td>
                                                <td class="px-4 py-2 border border-gray-300">{{ $row->port }}</td>
                                                <td class="px-4 py-2 border border-gray-300">{{ $row->vlan ?? '—' }}</td>
                                                <td class="px-4 py-2 border border-gray-300">{{ $row->first_seen_at->format('Y-m-d H:i') }}</td>
                                                <td class="px-4 py-2 border border-gray-300">{{ $row->last_seen_at->format('Y-m-d H:i') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @endif

                            @if ($arpHistory->isNotEmpty())
                                <table class="my-3 w-full table-auto divide-y divide-gray-200 border border-gray-300 text-sm">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-2 text-left border border-gray-300">IP</th>
                                            <th class="px-4 py-2 text-left border border-gray-300">VLAN</th>
                                            <th class="px-4 py-2 text-left border border-gray-300">First seen</th>
                                            <th class="px-4 py-2 text-left border border-gray-300">Last seen</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach ($arpHistory as $row)
                                            <tr>
                                                <td class="px-4 py-2 border border-gray-300">{{ $row->ip_address }}</td>
                                                <td class="px-4 py-2 border border-gray-300">{{ $row->vlan ?? '—' }}</td>
                                                <td class="px-4 py-2 border border-gray-300">{{ $row->first_seen_at->format('Y-m-d H:i') }}</td>
                                                <td class="px-4 py-2 border border-gray-300">{{ $row->last_seen_at->format('Y-m-d H:i') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @endif
                        </details>
                    @endif
                @endif
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-lg font-semibold">{{ __('Devices') }}</h3>
                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-2 text-sm">
                            <button type="button" id="devices-select-all" class="text-blue-600 hover:underline">{{ __('Select all') }}</button>
                            <span class="text-gray-300">|</span>
                            <button type="button" id="devices-select-none" class="text-blue-600 hover:underline">{{ __('Select none') }}</button>
                            <span class="text-gray-300">|</span>
                            <button type="button" id="devices-invert-selection" class="text-blue-600 hover:underline">{{ __('Invert selection') }}</button>
                        </div>
                        <a href="{{ route('network-lookup.devices.create') }}" class="text-blue-600 hover:underline text-sm">{{ __('Add device') }}</a>
                        <a href="{{ route('network-lookup.devices.import.show') }}" class="text-blue-600 hover:underline text-sm">{{ __('Import devices') }}</a>
                        <form action="{{ route('network-lookup.poll') }}" method="POST">
                            @csrf
                            <x-primary-button>{{ __('Poll now') }}</x-primary-button>
                        </form>
                    </div>
                </div>

                @if ($latestRun)
                    <p class="text-sm text-gray-600 mb-4">
                        {{ __('Latest poll run') }} #{{ $latestRun->id }}
                        ({{ $latestRun->started_at->diffForHumans() }}):
                        <span class="{{ $latestRunReportedCount === $latestRun->device_count ? 'text-green-600' : 'text-amber-600' }} font-medium">
                            {{ $latestRunReportedCount }}/{{ $latestRun->device_count }}
                        </span>
                        {{ __('devices reported') }}
                        @if ($latestRunReportedCount < $latestRun->device_count)
                            <span class="text-amber-600">({{ __('still running or some jobs never completed') }})</span>
                        @endif
                    </p>
                @endif

                <table class="w-full table-auto divide-y divide-gray-200 border border-gray-300 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left border border-gray-300">Name</th>
                            <th class="px-4 py-2 text-left border border-gray-300">Mgmt IP</th>
                            <th class="px-4 py-2 text-left border border-gray-300">Vendor</th>
                            <th class="px-4 py-2 text-left border border-gray-300">Role</th>
                            <th class="px-4 py-2 text-left border border-gray-300">Enabled</th>
                            <th class="px-4 py-2 text-left border border-gray-300">Last polled</th>
                            <th class="px-4 py-2 text-left border border-gray-300">Run #</th>
                            <th class="px-4 py-2 text-left border border-gray-300">Status</th>
                            <th class="px-4 py-2 text-left border border-gray-300">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach ($devices as $device)
                            <tr>
                                <td class="px-4 py-2 border border-gray-300">{{ $device->name }}</td>
                                <td class="px-4 py-2 border border-gray-300">{{ $device->mgmt_ip }}</td>
                                <td class="px-4 py-2 border border-gray-300">{{ $device->vendor }}</td>
                                <td class="px-4 py-2 border border-gray-300">{{ $device->role }}</td>
                                <td class="px-4 py-2 border border-gray-300">
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="checkbox" class="toggle-enabled" data-id="{{ $device->id }}" {{ $device->enabled ? 'checked' : '' }}>
                                        <span class="ml-2">{{ $device->enabled ? 'On' : 'Off' }}</span>
                                    </label>
                                </td>
                                <td class="px-4 py-2 border border-gray-300">{{ $device->last_polled_at?->diffForHumans() ?? '—' }}</td>
                                <td class="px-4 py-2 border border-gray-300 {{ $latestRun && $device->last_poll_run_id !== $latestRun->id && $device->enabled ? 'text-amber-600 font-medium' : '' }}">
                                    {{ $device->last_poll_run_id ?? '—' }}
                                </td>
                                <td class="px-4 py-2 border border-gray-300">
                                    @if ($device->last_poll_status === 'ok')
                                        <span class="text-green-600">OK</span>
                                    @elseif ($device->last_poll_status === 'error')
                                        <span class="text-red-600" title="{{ $device->last_poll_error }}">Error</span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 border border-gray-300 whitespace-nowrap">
                                    <a href="{{ route('network-lookup.devices.edit', $device) }}" class="text-blue-600 inline-block align-middle" title="{{ __('Edit') }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                        </svg>
                                    </a>
                                    <form action="{{ route('network-lookup.devices.destroy', $device) }}" method="POST" class="inline-block ml-2 align-middle" onsubmit="return confirm('Remove {{ $device->name }}? This cannot be undone.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600" title="{{ __('Delete') }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                            </svg>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</x-app-layout>
