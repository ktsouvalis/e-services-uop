@push('title')
    <title>Λεπτομέρειες Ειδοποίησης</title>
@endpush
<x-app-layout>
    {{-- <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Λεπτομέρειες Ειδοποίησης') }}
        </h2>
    </x-slot> --}}

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="flex items-center mb-4">
                    <!-- Heroicon: Bell -->
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    <span class="text-lg font-semibold">Λεπτομέρειες</span>
                </div>
                <div class="mb-4 text-gray-700">
                    {!! $notification->data['message'] !!}
                </div>
                <div class="text-sm text-gray-500 mb-6">
                    {{ $notification->created_at }}
                </div>
                <a href="{{route('notifications.index')}}" class="inline-flex items-center px-4 py-2 bg-blue-100 text-blue-900 font-semibold rounded hover:bg-blue-200 transition">
                    <!-- Heroicon: Arrow Left -->
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    Επιστροφή στις Ειδοποιήσεις
                </a>
            </div>
        </div>
    </div>
</x-app-layout>