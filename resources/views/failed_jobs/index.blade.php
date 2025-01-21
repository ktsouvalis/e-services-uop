<!-- resources/views/failed_jobs/index.blade.php -->
@push('title')
    <title>Failed Jobs</title>
@endpush
@push('links')
<style>
        .table td {
            word-wrap: break-word;
            max-width: 200px;
        }
    </style>
@endpush

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Failed Jobs') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Section 1: List of MailToDepartments -->
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">{{ __('Failed Jobs List') }}</h3>
                
                @if($failedJobs->isEmpty())
                    <p>{{ __('No entries found.') }}</p>
                @else
                <table class="text-center w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-6 py-3  text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            
                            <th class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Payload</th>
                            <th class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Exception</th>
                            <th class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Failed At</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach ($failedJobs as $job)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">{{ $job->id }}</td>
                                <td class="px-6 py-4" style="word-wrap: break-word; max-width: 300px;">{{ Illuminate\Support\Str::limit($job->payload, 200) }}</td>
                                <td class="px-6 py-4" style="word-wrap: break-word; max-width: 300px;">{{ Illuminate\Support\Str::limit($job->exception, 200) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ $job->failed_at }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
