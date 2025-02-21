<!-- resources/views/mailers/edit.blade.php -->
@push('title')
    <title>Edit User</title>
@endpush
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit User') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">{{ __('Edit User') }}</h3>

                <form id="user-form" action="{{ route('users.update', $user->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <!-- Name Field -->
                    <div class="mb-4">
                        <label for="name" class="block text-sm font-medium text-gray-700">{{ __('Name') }}</label>
                        <input type="text" name="name" id="name" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" value="{{ old('name', $user->name) }}">
                        @error('name')
                            <span class="text-red-500 text-sm">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- username Field -->
                    <div class="mb-4">
                        <label for="username" class="block text-sm font-medium text-gray-700">{{ __('Username') }}</label>
                        <input type="text" name="username" id="username" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" value="{{ old('username', $user->username) }}">
                        @error('username')
                            <span class="text-red-500 text-sm">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- Admin Field -->
                    <div class="mb-4">
                        <label for="admin" class="block text-sm font-medium text-gray-700">{{ __('Admin') }}</label>
                        <input type="checkbox" name="admin" id="admin" class="mt-1 block border-gray-300 rounded-md shadow-sm" {{ old('admin', $user->admin) ? 'checked' : '' }}>
                        @error('admin')
                            <span class="text-red-500 text-sm">{{ $message }}</span>
                        @enderror
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <a href="{{ route('users.index') }}" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                            {{ __('Επιστροφή') }}
                        </a>
                        <a href="{{route('users.edit', $user->id)}}" class="bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                            {{ __('Αναίρεση αλλαγών') }}
                        </a>
                        <x-primary-button>
                          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mr-2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                          </svg>                          
                          {{ __('Update') }}
                        </x-primary-button>
                    </div>
                </form>
                <hr class=my-2>        
              </div>
            </div>
        </div>
</x-app-layout>
