<x-app-layout>
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <!-- Section 1: List of MailToDepartments -->
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <h3 class="text-lg font-semibold mb-4">{{ __('Users List') }}</h3>
            <table class="text-center w-full divide-y divide-gray-200">
                <thead>
                    <tr>
                        <th class="px-6 py-3  text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                        <th class="px-6 py-3  text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3  text-xs font-medium text-gray-500 uppercase tracking-wider">UoP Username</th>
                        <th class="px-6 py-3  text-xs font-medium text-gray-500 uppercase tracking-wider">Admin</th>
                        <th class="px-6 py-3  text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($users as $user)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">{{ $user->id }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">{{ $user->name }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">{{ $user->username }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">@php echo ($user->admin ? 'NAI':'ΟΧΙ') @endphp</td>
                            <td class="px-6 py-4 flex justify-center">
                                
                                <a href="{{ route('users.edit', $user->id) }}" class="text-blue-600" >
                                    <svg 
                                        xmlns="http://www.w3.org/2000/svg" 
                                        fill="none" 
                                        viewBox="0 0 24 24" 
                                        stroke-width="1.2" 
                                        stroke="currentColor" 
                                        class="w-6 h-6">
                                        <path 
                                            stroke-linecap="round"
                                            stroke-linejoin="round" 
                                            d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" 
                                        />
                                    </svg>
                                        
                                </a>
                                
                                <form action="{{ route('users.destroy', $user->id) }}" method="POST" class="inline-block">
                                    @csrf
                                    @method('DELETE')
                                    {{-- <button type="submit" class="text-red-600 hover:text-red-900 ml-4" onclick="return confirm('Are you sure?')">{{ __('Delete') }}</button> --}}
                                    <button type="submit" class="text-red-600 ml-4" onclick="return confirm('Are you sure?')">
                                        <svg 
                                            xmlns="http://www.w3.org/2000/svg" 
                                            fill="none" 
                                            viewBox="0 0 24 24" 
                                            stroke-width="1.5" 
                                            stroke="currentColor" 
                                            class="size-6">
                                            <path 
                                                stroke-linecap="round" 
                                                stroke-linejoin="round" 
                                                d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" 
                                            />
                                        </svg>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <hr class="mt-6 mb-6">
    <!-- Section 2: Create Form -->
    <div class="max-w-5xl mx-auto bg-white shadow-sm sm:rounded-lg p-6">
        <h3 class="text-lg font-semibold mb-4">{{ __('Create New User') }}</h3>
        <form action="{{ route('users.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="flex flex-wrap -mx-3 mb-6">
                <!-- Name Field -->
                <div class="w-full md:w-1/2 px-3 mb-6 md:mb-0">
                    <label for="name" class="block text-sm font-medium text-gray-700">{{ __('Name') }}</label>
                    <input type="text" name="name" id="name" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" value="{{ old('name') }}">
                    @error('name')
                        <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>

                <!-- UoP Username Field -->
                <div class="w-full md:w-1/2 px-3">
                    <label for="username" class="block text-sm font-medium text-gray-700">{{ __('Username') }}</label>
                    <input type="text" name="username" id="username" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" value="{{ old('username') }}">
                    @error('username')
                        <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="flex flex-wrap -mx-3 mb-6">
                <!--Email Field -->
                {{-- <div class="w-full md:w-1/2 px-3">
                    <label for="email" class="block text-sm font-medium text-gray-700">{{ __('Email') }}</label>
                    <input type="email" name="email" id="email" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" value="{{ old('email') }}">
                    @error('email')
                        <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div> --}}

                <!-- Admin Field -->
                <div class="w-full md:w-1/2 px-3 mb-6 md:mb-0">
                    <label for="admin" class="block text-sm font-medium text-gray-700">{{ __('Admin') }}</label>
                    <input type="checkbox" name="admin" id="admin" class="mt-1 block border-gray-300 rounded-md shadow-sm" {{ old('admin') ? 'checked' : '' }}>
                </div>
            </div>
            <!-- Submit Button -->
            <div class="flex users-center justify-between">
                <x-primary-button>
                    {{ __('Δημιουργια') }}
                </x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
