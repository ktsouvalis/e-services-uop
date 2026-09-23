@push('title')
    <title>Add Newt Agent</title>
@endpush
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Add Newt Agent') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <form action="{{ route('pangolin.newt-agents.store') }}" method="POST">
                    @csrf

                    @include('pangolin.newt_agents._form')

                    <div class="flex items-center justify-end mt-4">
                        <x-primary-button>{{ __('Add agent') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
