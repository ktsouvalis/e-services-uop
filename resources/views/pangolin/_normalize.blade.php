<div class="bg-white shadow-sm sm:rounded-lg p-6 mb-6" x-data="{ apply: false }">
    <h3 class="text-lg font-semibold mb-4">{{ __('Normalize private resources') }}</h3>
    <p class="text-sm text-gray-500 mb-4">
        {{ __('Audits existing private resources against the naming convention and fixes drift. Defaults to a dry run — nothing changes unless Apply is checked and confirmed below.') }}
    </p>
    <form action="{{ route('pangolin.resources.normalize') }}" method="POST" class="space-y-4">
        @csrf
        <div>
            <label for="resource_ids" class="block text-sm font-medium text-gray-700">{{ __('Scope to niceIds or siteResourceIds (optional, comma-separated)') }}</label>
            <input type="text" name="resource_ids" id="resource_ids" placeholder="mkatsis-2302-50-p22-p3389, 130"
                   class="mt-1 block w-96 border-gray-300 rounded-md shadow-sm">
            @error('resource_ids')
                <span class="text-red-500 text-sm">{{ $message }}</span>
            @enderror
        </div>
        <div class="flex items-center">
            <input type="checkbox" name="apply" id="normalize_apply" value="1" x-model="apply"
                   class="rounded border-gray-300 text-indigo-600 shadow-sm">
            <label for="normalize_apply" class="ml-2 text-sm text-gray-700">{{ __('Apply changes (live renames/access grants — unchecked runs a dry run)') }}</label>
        </div>
        <div x-show="apply" x-cloak class="flex items-center p-3 bg-yellow-50 rounded-md">
            <input type="checkbox" name="confirm" id="normalize_confirm" value="1"
                   class="rounded border-gray-300 text-indigo-600 shadow-sm">
            <label for="normalize_confirm" class="ml-2 text-sm text-gray-700">
                {{ __('I understand this will rename resources and grant access live on the cluster.') }}
            </label>
        </div>
        @error('confirm')
            <span class="text-red-500 text-sm">{{ $message }}</span>
        @enderror
        <x-primary-button type="submit">{{ __('Run normalize') }}</x-primary-button>
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
                    <th class="px-3 py-2">{{ __('Scope') }}</th>
                    <th class="px-3 py-2">{{ __('Mode') }}</th>
                    <th class="px-3 py-2">{{ __('Status') }}</th>
                    <th class="px-3 py-2">{{ __('Summary') }}</th>
                    <th class="px-3 py-2">{{ __('Report') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($normalizeRuns as $run)
                    <tr>
                        <td class="px-3 py-2">{{ $run->created_at->diffForHumans() }}</td>
                        <td class="px-3 py-2">{{ optional($run->user)->name }}</td>
                        <td class="px-3 py-2 text-gray-500">
                            {{ !empty($run->options['resource_ids']) ? implode(', ', $run->options['resource_ids']) : __('all') }}
                        </td>
                        <td class="px-3 py-2">{{ ($run->options['apply'] ?? false) ? __('Apply') : __('Dry run') }}</td>
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
                        <td class="px-3 py-2 text-gray-500">
                            @if ($run->summary)
                                {{ collect($run->summary)->map(fn ($count, $status) => "{$status}: {$count}")->implode(', ') }}
                            @endif
                        </td>
                        <td class="px-3 py-2">
                            @if ($run->report_path)
                                <a href="{{ route('pangolin.resources.download', $run) }}" class="text-indigo-600 hover:underline">{{ __('download') }}</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-3 py-6 text-center text-gray-500">{{ __('No normalize runs yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
