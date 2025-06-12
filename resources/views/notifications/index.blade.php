@push('title')
    <title>Ειδοποιήσεις</title>
@endpush
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Ειδοποιήσεις') }}
        </h2>
    </x-slot>

    @push('scripts')
        <script>
            var markNotificationAsReadUrl = '{{ route("notifications.mark_as_read", ["notification" =>"mpla"]) }}';
            var deleteNotificationUrl = '{{ route("notifications.destroy", ["notification" =>"mpla"]) }}';
        </script>
        <script src="{{asset('mark_notification_as_read.js')}}"></script>
        <script src="{{asset('delete_notification.js')}}"></script>
    @endpush

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                {{-- <h3 class="text-lg font-semibold mb-4">{{ __('Ειδοποιήσεις') }}</h3> --}}
                @if($notifications->count() == 0)
                    <div class="text-blue-700 bg-blue-100 border border-blue-200 rounded p-4">
                        Δεν υπάρχουν ειδοποιήσεις
                    </div>
                @else
                    <div class="flex flex-wrap gap-2 mb-4">
                        @if($notifications->whereNull('read_at')->count() > 1)
                            <form action="{{route("notifications.mark_all_as_read")}}" method="post">
                                @csrf
                                <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:outline-none focus:border-blue-700 focus:ring focus:ring-blue-200 active:bg-blue-600 transition">
                                    <!-- Heroicon: Check Circle -->
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2l4-4m6 2a9 9 0 11-18 0a9 9 0 0118 0z" />
                                    </svg>
                                    Σήμανση όλων ως αναγνωσμένα
                                </button>
                            </form>
                        @endif
                        @php
                            $user = Auth::user();
                        @endphp
                        <form action="{{route("notifications.delete_all", $user) }}" method="post">
                            @csrf
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 focus:outline-none focus:border-red-700 focus:ring focus:ring-red-200 active:bg-red-600 transition">
                                <!-- Heroicon: Trash -->
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M10 3h4a2 2 0 012 2v2H8V5a2 2 0 012-2z"/>
                                </svg>
                                Διαγραφή Όλων
                            </button>
                        </form>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-center">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Κατάσταση</th>
                                    <th class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Σύνοψη</th>
                                    <th class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Ημερομηνία</th>
                                    <th class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Διαγραφή</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach ($notifications as $notification)
                                @php
                                    $rowClass = $notification->read_at == null ? 'bg-green-100' : '';
                                @endphp
                                    <tr class="{{ $rowClass }}" id="notification-{{$notification->id}}">
                                        <td class="mark-{{$notification->id}} px-6 py-4 text-center">
                                            @if($notification->read_at == null)
                                                <!-- Heroicon: Envelope (unread) -->
                                                <svg id="icon{{$notification->id}}" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8m-18 8V8a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                                                </svg>
                                            @else
                                                <!-- Heroicon: Envelope Open (read) -->
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-400 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8m-18 8V8a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                                                </svg>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4">
                                            <a href="{{ route('notifications.show', $notification->id) }}" class="text-blue-700 hover:underline">
                                                {{ $notification->data['summary'] }}
                                            </a>
                                        </td>
                                        <td class="px-6 py-4">
                                            {{ $notification->created_at }}
                                        </td>
                                        <td class="px-6 py-4 text-center" id="actions{{$notification->id}}">
                                            @if($notification->read_at == null)
                                                <button id="mark{{$notification->id}}" class="mark-notification inline-flex items-center justify-center px-2 py-1 bg-blue-500 text-white rounded hover:bg-blue-600 mr-2" data-toggle="tooltip" title="Σήμανση ως Αναγνωσμένο" data-notification-id="{{ $notification->id }}">
                                                    <!-- Heroicon: Check Circle -->
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2l4-4m6 2a9 9 0 11-18 0a9 9 0 0118 0z" />
                                                    </svg>
                                                </button>
                                            @endif
                                            <button class="delete-notification inline-flex items-center justify-center px-2 py-1 bg-red-100 text-red-600 rounded hover:bg-red-200" data-notification-id="{{ $notification->id }}" data-toggle="tooltip" title="Διαγραφή">
                                                <!-- Heroicon: Trash -->
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M10 3h4a2 2 0 012 2v2H8V5a2 2 0 012-2z"/>
                                                </svg>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>