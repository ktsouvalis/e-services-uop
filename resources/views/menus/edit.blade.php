<!-- resources/views/menus/edit.blade.php -->
@push('title')
    <title>Edit Menu</title>
@endpush
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Menu') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">{{ __('Edit Menu') }}</h3>

                <form action="{{ route('menus.update', $menu->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <!-- Title Field -->
                    <div class="mb-4">
                        <label for="title" class="block text-sm font-medium text-gray-700">{{ __('title') }}</label>
                        <input type="text" name="title" id="title" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" value="{{ old('title', $menu->title) }}">
                        @error('name')
                            <span class="text-red-500 text-sm">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- Route Field -->
                    <div class="mb-4">
                        <label for="route" class="block text-sm font-medium text-gray-700">{{ __('Route') }}</label>
                        <input type="text" name="route" id="route" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" value="{{ old('route', $menu->route) }}">
                        @error('route')
                            <span class="text-red-500 text-sm">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- RouteIs Field -->
                    <div class="mb-4">
                        <label for="route_is" class="block text-sm font-medium text-gray-700">{{ __('RouteIs') }}</label>
                        <textarea name="route_is" id="route_is" rows="4" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">{{ old('route_is', $menu->route_is) }}</textarea>
                        @error('route_is')
                            <span class="text-red-500 text-sm">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="flex items-center justify-end mt-4">
                        <x-primary-button>
                            {{ __('Update') }}
                        </x-primary-button>
                    </div>
                </form>
                
            </div>
        </div>
    </div>
</x-app-layout>
