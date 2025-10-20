<!-- resources/views/mail_to_departments/index.blade.php -->
@push('title')
    <title>Sheet/Comma Mailers</title>
@endpush
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Sheet/Comma Mailers') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Section 1: List of MailToDepartments -->
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">{{ __('Sheet/Comma Mailers List') }}</h3>
                
                @if($sheetmailers->isEmpty())
                    <p>{{ __('No entries found.') }}</p>
                @else
                    <table class="text-center w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-6 py-3  text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                <th class="px-6 py-3  text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3  text-xs font-medium text-gray-500 uppercase tracking-wider">Creator</th>
                                <th class="px-6 py-3  text-xs font-medium text-gray-500 uppercase tracking-wider">Visibility</th>
                                <th class="px-6 py-3  text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($sheetmailers as $sheetmailer)
                                @can('view', $sheetmailer)
                                <tr>
                                    <td class="px-6 py-4 whitespace-normal text-center">{{ $sheetmailer->id }}</td>
                                    <td class="px-6 py-4 whitespace-normal text-center">{{ $sheetmailer->name }}</td>
                                    <td class="px-6 py-4 whitespace-normal text-center">{{ optional($sheetmailer->user)->name ?? '-' }}</td>
                                    <td class="px-6 py-4 whitespace-normal text-center">
                                        @if($sheetmailer->is_public)
                                            <span class="text-green-700">Public</span>
                                        @else
                                            <span class="text-gray-600">Private</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 flex justify-center">
                                        @can('update', $sheetmailer)
                                        <a href="{{ route('sheetmailers.edit', $sheetmailer->id) }}" class="text-blue-600" >
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
                                        @endcan

                                        @can('delete', $sheetmailer)
                                        <form action="{{ route('sheetmailers.destroy', $sheetmailer->id) }}" method="POST" class="inline-block">
                                            @csrf
                                            @method('DELETE')
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
                                        @endcan
                                    </td>
                                </tr>
                                @endcan
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            <!-- Section 2: Create Form -->
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">{{ __('Create New Sheet/Comma Mailer') }}</h3>
                <form action="{{ route('sheetmailers.store') }}" method="POST">
                    @csrf

                    <!-- Name Field -->
                    <div class="mb-4">
                        <label for="name" class="block text-sm font-medium text-gray-700">{{ __('Name') }}</label>
                        <input type="text" name="name" id="name" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" value="{{ old('name') }}">
                        @error('name')
                            <span class="text-red-500 text-sm">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- Submit Button -->
                    
                    <div class="flex items-center justify-end mt-4">
                        <x-primary-button>
                            {{ __('Create') }}
                        </x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
