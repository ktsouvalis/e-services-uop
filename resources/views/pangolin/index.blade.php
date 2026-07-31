@push('title')
    <title>Pangolin</title>
@endpush
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Pangolin') }}
        </h2>
    </x-slot>

    <div class="py-12" x-data="{ tab: '{{ request('tab', 'monitor') }}' }" x-init="$watch('tab', (t) => history.replaceState(null, '', '{{ route('pangolin.index') }}?tab=' + t))">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <div class="border-b border-gray-200 mb-6">
                <nav class="-mb-px flex space-x-8">
                    <button type="button" @click="tab = 'monitor'"
                            :class="tab === 'monitor' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        {{ __('Monitor') }}
                    </button>
                    <button type="button" @click="tab = 'logs'"
                            :class="tab === 'logs' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        {{ __('Logs') }}
                    </button>
                    <button type="button" @click="tab = 'import'"
                            :class="tab === 'import' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        {{ __('Import') }}
                    </button>
                    <button type="button" @click="tab = 'normalize'"
                            :class="tab === 'normalize' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        {{ __('Normalize') }}
                    </button>
                </nav>
            </div>

            <div x-show="tab === 'monitor'">
                @include('pangolin._monitor')
            </div>
            <div x-show="tab === 'logs'" x-cloak>
                @include('pangolin._logs')
            </div>
            <div x-show="tab === 'import'" x-cloak>
                @include('pangolin._import')
            </div>
            <div x-show="tab === 'normalize'" x-cloak>
                @include('pangolin._normalize')
            </div>
        </div>
    </div>
</x-app-layout>
