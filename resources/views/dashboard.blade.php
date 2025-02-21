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
                    <div class="mt-12">
                        <span class="inline-flex items-center rounded-md bg-red-50 px-2 py-1 text-xs font-medium text-red-700 ring-1 ring-red-600/10 ring-inset"> 
                            <a href="{{ route('users.index') }}">Users</a>
                        </span>
                        <span class="inline-flex items-center rounded-md bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-700 ring-1 ring-indigo-700/10 ring-inset">
                            <a href="{{ route('menus.index') }}">Menus</a>
                        </span>
                        <span class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-green-600/20 ring-inset">
                            <a href="{{ url('/jobs') }}">Jobs</a>
                        </span>
                    </div>
                    @else
                        Καλωσήρθατε {{Auth::user()->name }}
                    @endif
                </div>
            </div>
        </div>
        
    </div>
</x-app-layout>
