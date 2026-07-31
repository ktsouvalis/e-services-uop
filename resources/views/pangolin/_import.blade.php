<div class="bg-white shadow-sm sm:rounded-lg p-6 mb-6">
    <h3 class="text-lg font-semibold mb-4">{{ __('Mass import private resources') }}</h3>
    <p class="text-sm text-gray-500 mb-4">
        {{ __('Upload a filled-in request sheet (see the template\'s Instructions tab). Each User Emails entry becomes its own site resource, spanning every site in the org for HA.') }}
    </p>
    <form action="{{ route('pangolin.resources.import') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
        @csrf
        <div>
            <label for="import_file" class="block text-sm font-medium text-gray-700">{{ __('Requests file (.xlsx)') }}</label>
            <input type="file" name="file" id="import_file" accept=".xlsx" required
                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            @error('file')
                <span class="text-red-500 text-sm">{{ $message }}</span>
            @enderror
        </div>
        <div class="flex items-center">
            <input type="hidden" name="dry_run" value="0">
            <input type="checkbox" name="dry_run" id="import_dry_run" value="1" checked
                   class="rounded border-gray-300 text-indigo-600 shadow-sm">
            <label for="import_dry_run" class="ml-2 text-sm text-gray-700">{{ __('Dry run (recommended first)') }}</label>
        </div>
        <x-primary-button type="submit">{{ __('Run import') }}</x-primary-button>
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
                    <th class="px-3 py-2">{{ __('File') }}</th>
                    <th class="px-3 py-2">{{ __('Mode') }}</th>
                    <th class="px-3 py-2">{{ __('Status') }}</th>
                    <th class="px-3 py-2">{{ __('Summary') }}</th>
                    <th class="px-3 py-2">{{ __('Report') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($importRuns as $run)
                    <tr>
                        <td class="px-3 py-2">{{ $run->created_at->diffForHumans() }}</td>
                        <td class="px-3 py-2">{{ optional($run->user)->name }}</td>
                        <td class="px-3 py-2 text-gray-500">
                            <div class="max-w-[180px] truncate" title="{{ $run->options['original_filename'] ?? '' }}">{{ $run->options['original_filename'] ?? '—' }}</div>
                        </td>
                        <td class="px-3 py-2">{{ ($run->options['dry_run'] ?? false) ? __('Dry run') : __('Live') }}</td>
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
                        <td colspan="7" class="px-3 py-6 text-center text-gray-500">{{ __('No imports yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
