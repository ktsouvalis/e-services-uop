<div class="bg-white shadow-sm sm:rounded-lg p-6 mb-6">
    <h3 class="text-lg font-semibold mb-4">{{ __('Fetch cluster logs') }}</h3>
    <p class="text-sm text-gray-500 mb-4">{{ __('Pulls logs at or above the chosen level from every node over SSH (docker logs for containerised services, journalctl for bare-metal ones).') }}</p>
    <form action="{{ route('authentik.logs.fetch') }}" method="POST" class="flex items-end gap-4">
        @csrf
        <div>
            <label for="lookback_hours" class="block text-sm font-medium text-gray-700">{{ __('Lookback (hours)') }}</label>
            <input type="number" name="lookback_hours" id="lookback_hours" min="1" max="168" placeholder="24"
                   class="mt-1 block w-32 border-gray-300 rounded-md shadow-sm">
            @error('lookback_hours')
                <span class="text-red-500 text-sm">{{ $message }}</span>
            @enderror
        </div>
        <div>
            <label for="level" class="block text-sm font-medium text-gray-700">{{ __('Minimum level') }}</label>
            <select name="level" id="level" class="mt-1 block w-32 border-gray-300 rounded-md shadow-sm">
                <option value="error">{{ __('Error') }}</option>
                <option value="warning" selected>{{ __('Warning') }}</option>
                <option value="info">{{ __('Info') }}</option>
                <option value="debug">{{ __('Debug') }}</option>
            </select>
            @error('level')
                <span class="text-red-500 text-sm">{{ $message }}</span>
            @enderror
        </div>
        <x-primary-button type="submit">{{ __('Fetch logs') }}</x-primary-button>
    </form>
</div>

<div class="bg-white shadow-sm sm:rounded-lg p-6">
    <h3 class="text-lg font-semibold mb-4">{{ __('Recent runs') }}</h3>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead>
                <tr class="text-left text-xs font-medium text-gray-500 uppercase">
                    <th class="px-3 py-2">{{ __('Requested') }}</th>
                    <th class="px-3 py-2">{{ __('By') }}</th>
                    <th class="px-3 py-2">{{ __('Status') }}</th>
                    <th class="px-3 py-2">{{ __('File') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($logRuns as $run)
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
                            @if ($run->error)
                                <span class="text-xs text-red-500 block">{{ $run->error }}</span>
                            @endif
                        </td>
                        <td class="px-3 py-2">
                            @if ($run->report_path)
                                <a href="{{ route('authentik.logs.download', $run) }}" class="text-indigo-600 hover:underline">{{ __('log') }}</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-3 py-6 text-center text-gray-500">{{ __('No log fetches yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
