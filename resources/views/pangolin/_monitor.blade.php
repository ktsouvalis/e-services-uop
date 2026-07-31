<div class="bg-white shadow-sm sm:rounded-lg p-6">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold">{{ __('Cluster status') }}</h3>
        <form action="{{ route('pangolin.monitor.refresh') }}" method="POST">
            @csrf
            <x-secondary-button type="submit">{{ __('Refresh now') }}</x-secondary-button>
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200" id="pangolin-monitor-table">
            <thead>
                <tr class="text-left text-xs font-medium text-gray-500 uppercase">
                    <th class="px-3 py-2">{{ __('Service') }}</th>
                    <th class="px-3 py-2">{{ __('Node') }}</th>
                    <th class="px-3 py-2">{{ __('IP') }}</th>
                    <th class="px-3 py-2">{{ __('Status') }}</th>
                    <th class="px-3 py-2">{{ __('Role') }}</th>
                    <th class="px-3 py-2">{{ __('Details') }}</th>
                    <th class="px-3 py-2">{{ __('Checked at') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($statuses as $service => $rows)
                    @foreach ($rows as $row)
                        <tr data-service="{{ $row->service }}" data-ip="{{ $row->node_ip }}">
                            <td class="px-3 py-2 font-medium">{{ $row->service }}</td>
                            <td class="px-3 py-2">{{ $row->node_name }}</td>
                            <td class="px-3 py-2 text-gray-500">{{ $row->node_ip }}</td>
                            <td class="px-3 py-2" data-field="status">
                                <span class="px-2 py-1 rounded text-xs font-semibold
                                    @class([
                                        'bg-green-100 text-green-800' => $row->status === 'up',
                                        'bg-yellow-100 text-yellow-800' => $row->status === 'degraded',
                                        'bg-red-100 text-red-800' => $row->status === 'down',
                                        'bg-gray-100 text-gray-600' => !in_array($row->status, ['up', 'degraded', 'down']),
                                    ])">{{ $row->status }}</span>
                            </td>
                            <td class="px-3 py-2" data-field="role">{{ $row->role }}</td>
                            <td class="px-3 py-2 text-gray-500" data-field="message">{{ $row->message ?: $row->metrics_summary }}</td>
                            <td class="px-3 py-2 text-gray-400" data-field="checked_at">{{ optional($row->checked_at)->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                @empty
                    <tr>
                        <td colspan="7" class="px-3 py-6 text-center text-gray-500">
                            {{ __('No data yet — click Refresh now, or wait for the scheduled poll.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<script>
    (function () {
        const statusClasses = {
            up: 'bg-green-100 text-green-800',
            degraded: 'bg-yellow-100 text-yellow-800',
            down: 'bg-red-100 text-red-800',
        };

        function applyUpdate(row) {
            const tr = document.querySelector(`#pangolin-monitor-table tr[data-service="${row.service}"][data-ip="${row.node_ip}"]`);
            if (!tr) return;

            const statusCell = tr.querySelector('[data-field="status"] span');
            if (statusCell) {
                statusCell.textContent = row.status;
                statusCell.className = 'px-2 py-1 rounded text-xs font-semibold ' + (statusClasses[row.status] || 'bg-gray-100 text-gray-600');
            }
            tr.querySelector('[data-field="role"]').textContent = row.role ?? '';
            tr.querySelector('[data-field="message"]').textContent = row.message || row.metrics_summary || '';
            if (row.checked_at) {
                tr.querySelector('[data-field="checked_at"]').textContent = 'just now';
            }
        }

        function poll() {
            fetch('{{ route('pangolin.monitor.data') }}', { headers: { 'Accept': 'application/json' } })
                .then((r) => r.json())
                .then((grouped) => Object.values(grouped).flat().forEach(applyUpdate))
                .catch(() => {});
        }

        setInterval(poll, 15000);
    })();
</script>
