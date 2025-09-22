<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    
                    @if(Auth::check() and Auth::user()->admin)
                    {{ __("Διαχειριστικές Λειτουργίες") }}
                    <div class="mt-12 mb-3">
                        <span class="inline-flex items-center rounded-md bg-red-50 px-2 py-1 text-xs font-medium text-red-700 ring-1 ring-red-600/10 ring-inset"> 
                            <a href="{{ route('users.index') }}">Users</a>
                        </span>
                        <span class="inline-flex items-center rounded-md bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-700 ring-1 ring-indigo-700/10 ring-inset">
                            <a href="{{ route('menus.index') }}">Menus</a>
                        </span>
                        <span class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-green-600/20 ring-inset">
                            <a href="{{ url('/jobs') }}">Jobs</a>
                        </span>
                        <span class="inline-flex items-center rounded-md bg-yellow-50 px-2 py-1 text-xs font-medium text-yellow-700 ring-1 ring-yellow-600/20 ring-inset">
                            <a href="{{ route('aimodels.index') }}">AI Models</a>
                        </span>
                    </div>
                    <hr>
                    {{-- Compact inline form: small input and button group so it doesn't expand the container --}}
                        <form class="inline-flex items-center space-x-2 py-2" method="GET" action="{{ url('/get_logs') }}">
                            <label for="date" class="sr-only">Date</label>
                            <input type="date" id="date" name="date" value="{{ old('date', date('Y-m-d')) }}" required
                                class="w-36 border border-gray-300 rounded-md px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />

                            <button type="submit" class="inline-flex items-center rounded-md bg-fuchsia-50 px-2 py-1 text-xs font-medium text-fuchsia-700 ring-1 ring-fuchsia-700/10 ring-inset">
                                <span class="ml-2">Download Logs</span>
                            </button>
                        </form>
                    @else
                        Καλωσήρθατε {{Auth::user()->name }}
                    @endif
                </div>
            </div>
        </div>
        
    </div>
</x-app-layout>
