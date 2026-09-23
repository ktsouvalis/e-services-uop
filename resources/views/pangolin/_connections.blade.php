<div class="bg-white shadow-sm sm:rounded-lg p-6 mb-6">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h3 class="text-lg font-semibold">{{ __('Newt connections') }}</h3>
            <p class="text-sm text-gray-500">{{ __('Which user connected to which resource, resolved from each Newt agent\'s access log.') }}</p>
        </div>
        <form action="{{ route('pangolin.newt-connections.fetch') }}" method="POST">
            @csrf
            <x-primary-button>{{ __('Fetch now') }}</x-primary-button>
        </form>
    </div>

    <form action="{{ route('pangolin.index') }}" method="GET" class="grid grid-cols-1 sm:grid-cols-6 gap-3 mb-6">
        <input type="hidden" name="tab" value="connections">
        <div class="sm:col-span-2">
            <label for="filter_user" class="block text-xs font-medium text-gray-700">{{ __('User (name or email)') }}</label>
            <input type="text" name="user" id="filter_user" value="{{ request('user') }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
        </div>
        <div>
            <label for="filter_agent" class="block text-xs font-medium text-gray-700">{{ __('Agent') }}</label>
            <select name="agent_id" id="filter_agent" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                <option value="">{{ __('Any') }}</option>
                @foreach ($newtAgents as $agent)
                    <option value="{{ $agent->id }}" {{ (string) request('agent_id') === (string) $agent->id ? 'selected' : '' }}>{{ $agent->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="filter_proto" class="block text-xs font-medium text-gray-700">{{ __('Protocol') }}</label>
            <select name="proto" id="filter_proto" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                <option value="">{{ __('Any') }}</option>
                <option value="tcp" {{ request('proto') === 'tcp' ? 'selected' : '' }}>TCP</option>
                <option value="udp" {{ request('proto') === 'udp' ? 'selected' : '' }}>UDP</option>
            </select>
        </div>
        <div>
            <label for="filter_from" class="block text-xs font-medium text-gray-700">{{ __('From') }}</label>
            <input type="date" name="from" id="filter_from" value="{{ request('from') }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
        </div>
        <div>
            <label for="filter_to" class="block text-xs font-medium text-gray-700">{{ __('To') }}</label>
            <input type="date" name="to" id="filter_to" value="{{ request('to') }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
        </div>
        <div class="sm:col-span-6 flex justify-end gap-3">
            @if (request()->hasAny(['user', 'agent_id', 'proto', 'from', 'to']))
                <a href="{{ route('pangolin.index', ['tab' => 'connections']) }}" class="text-sm text-gray-500 self-center hover:underline">{{ __('Clear filters') }}</a>
            @endif
            <x-primary-button>{{ __('Filter') }}</x-primary-button>
        </div>
    </form>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead>
                <tr class="text-left text-xs font-medium text-gray-500 uppercase">
                    <th class="px-3 py-2">{{ __('Started (Athens time)') }}</th>
                    <th class="px-3 py-2">{{ __('Duration') }}</th>
                    <th class="px-3 py-2">{{ __('Who') }}</th>
                    <th class="px-3 py-2">{{ __('Client') }}</th>
                    <th class="px-3 py-2">{{ __('Site') }}</th>
                    <th class="px-3 py-2">{{ __('Resource') }}</th>
                    <th class="px-3 py-2">{{ __('Proto') }}</th>
                    <th class="px-3 py-2">{{ __('Destination') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($connections as $connection)
                    <tr>
                        <td class="px-3 py-2 ">{{ $connection->started_at_local->format('Y-m-d H:i:s') }}</td>
                        <td class="px-3 py-2 whitespace-nowrap">
                            @if ($connection->ended_at)
                                {{ $connection->started_at->diffForHumans($connection->ended_at, true) }}
                            @else
                                <span class="text-amber-600">{{ __('ongoing') }}</span>
                            @endif
                        </td>
                        <td class="px-3 py-2">{{ $connection->user_name ?: ($connection->user_email ?: $connection->src_ip) }}</td>
                        <td class="px-3 py-2 text-gray-500">{{ $connection->client_name ?: '—' }}</td>
                        <td class="px-3 py-2">{{ $connection->site_name ?: "site#{$connection->resource_id}" }}</td>
                        <td class="px-3 py-2 text-gray-500">{{ $connection->resource_name ?: '—' }}</td>
                        <td class="px-3 py-2 uppercase text-gray-500">{{ $connection->proto }}</td>
                        <td class="px-3 py-2 text-gray-500">{{ $connection->dst_ip }}:{{ $connection->dst_port }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-3 py-6 text-center text-gray-500">{{ __('No connections recorded yet — click "Fetch now" to pull from the configured Newt agents.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">
        {{ $connections->links() }}
    </div>
</div>

<div class="bg-white shadow-sm sm:rounded-lg p-6">
    <h3 class="text-lg font-semibold mb-4">{{ __('Recent fetches') }}</h3>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead>
                <tr class="text-left text-xs font-medium text-gray-500 uppercase">
                    <th class="px-3 py-2">{{ __('Requested') }}</th>
                    <th class="px-3 py-2">{{ __('By') }}</th>
                    <th class="px-3 py-2">{{ __('Status') }}</th>
                    <th class="px-3 py-2">{{ __('Synced') }}</th>
                    <th class="px-3 py-2">{{ __('Agent errors') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($newtConnectionRuns as $run)
                    <tr>
                        <td class="px-3 py-2">{{ $run->created_at->diffForHumans() }}</td>
                        <td class="px-3 py-2">{{ optional($run->user)->name }}</td>
                        <td class="px-3 py-2">
                            <span class="px-2 py-1 rounded text-xs font-semibold
                                @class([
                                    'bg-green-100 text-green-800' => $run->status === 'completed',
                                    'bg-red-100 text-red-800' => $run->status === 'failed',
                                    'bg-gray-100 text-gray-600' => in_array($run->status, ['queued', 'running']),
                                ])">{{ $run->status }}</span>
                        </td>
                        <td class="px-3 py-2 text-gray-500">{{ $run->summary['synced'] ?? '—' }}</td>
                        <td class="px-3 py-2 text-red-500">
                            @if (! empty($run->summary['agent_errors']))
                                {{ implode('; ', $run->summary['agent_errors']) }}
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-3 py-6 text-center text-gray-500">{{ __('No fetches yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
