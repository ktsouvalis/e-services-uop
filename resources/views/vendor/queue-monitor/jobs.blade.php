<!-- filepath: /c:/Users/kosta/Desktop/Dev/e-services-uop/resources/views/vendor/queue-monitor/jobs.blade.php -->
<x-app-layout>
    @if(config('queue-monitor.ui.refresh_interval'))
        @push('links')
            <meta http-equiv="refresh" content="{{ config('queue-monitor.ui.refresh_interval') }}">
        @endpush
    @endif
    @push('title')
        <title>Ουρά Εργασιών</title>
    @endpush
    @push('links')
        <link href="{{ asset('vendor/queue-monitor/app.css') }}" rel="stylesheet">
    @endpush
<div class="font-sans pb-64 bg-white dark:bg-gray-800 dark:text-white">
    <nav class="flex items-center py-4 border-b border-gray-100 dark:border-gray-600">
        <h1 class="px-4 w-full font-semibold text-lg">
            @lang('Queue Monitor')
        </h1>
        <div class="w-[24rem] px-4 text-sm text-gray-700 font-light">
            Statistics
        </div>
    </nav>
    <main class="flex">
        <article class="w-full p-4">
            <h2 class="mb-4 text-gray-800 text-sm font-medium">
                @lang('Filter')
            </h2>
            @include('queue-monitor::partials.filter', [
                'filters' => $filters,
            ])
            <h2 class="mb-4 text-gray-800 text-sm font-medium">
                @lang('Jobs')
            </h2>
            @if($jobs->isEmpty())
                <div class="text-center text-gray-600 dark:text-gray-400">
                    @lang('No jobs to display.')
                </div>
            @else
                @include('queue-monitor::partials.table', [
                    'jobs' => $jobs,
                ])
            @endif
            @if(config('queue-monitor.ui.allow_purge'))
                <div class="mt-12">
                    <form action="{{ route('queue-monitor::purge') }}" method="post">
                        @csrf
                        @method('delete')
                        <button class="py-2 px-4 bg-red-50 dark:bg-red-200 hover:dark:bg-red-300 hover:bg-red-100 text-red-800 text-xs font-medium rounded-md transition-colors duration-150">
                            @lang('Delete all entries')
                        </button>
                    </form>
                </div>
            @endif
        </article>
        <aside class="flex flex-col gap-4 w-[24rem] p-4">
            @foreach($metrics->all() as $metric)
                @include('queue-monitor::partials.metrics-card', [
                    'metric' => $metric,
                ])
            @endforeach
        </aside>
    </main>
</div>
</x-app-layout>