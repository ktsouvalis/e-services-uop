<x-app-layout>
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <!-- Section 1: List of AI Models -->
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <h3 class="text-lg font-semibold mb-4">{{ __('AI Models List') }}</h3>
            <table class="text-center w-full divide-y divide-gray-200">
                <thead>
                    <tr>
                        <th class="px-6 py-3  text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                        <th class="px-6 py-3  text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3  text-xs font-medium text-gray-500 uppercase tracking-wider">Source</th>
                        <th class="px-6 py-3  text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                        <th class="px-6 py-3  text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($aimodels as $aimodel)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">{{ $aimodel->id }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">{{ $aimodel->name }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">{{ $aimodel->source }}</td>
                            <td class="px-6 py-4 whitespace-wrap">{{ $aimodel->description }}</td>
                            <td class="px-6 py-4 flex justify-center">
                                
                                <a href="{{ route('aimodels.edit', $aimodel->id) }}" class="text-blue-600" >
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
                                
                                <form action="{{ route('aimodels.destroy', $aimodel->id) }}" method="POST" class="inline-block">
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
        <h3 class="text-lg font-semibold mb-4">{{ __('Create New AI Model') }}</h3>
        <form action="{{ route('aimodels.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="flex flex-wrap -mx-3 mb-6">
                <!-- Name Field -->
                <div class="w-full md:w-1/2 px-3 mb-6 md:mb-0">
                    <label for="name" class="block text-sm font-medium text-gray-700">{{ __('Name') }}</label>
                    <input type="text" name="name" id="name" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" placeholder="Study your api to be sure" value="{{ old('name') }}">
                    @error('name')
                        <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Source Field -->
                <div class="w-full md:w-1/2 px-3 mb-6 md:mb-0">
                    <label for="source" class="block text-sm font-medium text-gray-700">{{ __('Source') }}</label>
                    <input type="text" name="source" id="source" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" placeholder="openai or deepseek" value="{{ old('source') }}">
                    @error('source')
                        <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Description Field -->
                <div class="w-full md:w-1/2 px-3">
                    <label for="description" class="block text-sm font-medium text-gray-700">{{ __('Description') }}</label>
                    <textarea name="description" id="description" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>{{ old('description') }}</textarea>
                    @error('description')
                        <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Properties checkboxes -->
                <div class="w-full md:w-1/2 px-3">
                    <label for="properties" class="block text-sm font-medium text-gray-700">{{ __('Properties') }}</label>
                    <div class="mt-1 grid grid-cols-1 gap-y-4">
                        <div class="flex items-center">
                            <input type="checkbox" name="properties[]" value="accepts_developer_messages" id="accepts_developer_messages" class="rounded text-blue-600">
                            <label for="accepts_developer_messages" class="ml-2 text-sm text-gray-700"> Accepts Developer Messages </label>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" name="properties[]" value="accepts_system_messages" id="accepts_system_messages" class="rounded text-blue-600">
                            <label for="accepts_system_messages" class="ml-2 text-sm text-gray-700"> Accepts System Messages </label>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" name="properties[]" value="reasoning_effort" id="reasoning_effort" class="rounded text-blue-600">
                            <label for="reasoning_effort" class="ml-2 text-sm text-gray-700"> Accepts Reasoning Effort </label>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" name="properties[]" value="accepts_audio" id="accepts_audio" class="rounded text-blue-600">
                            <label for="accepts_audio" class="ml-2 text-sm text-gray-700"> Accepts Audio </label>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" name="properties[]" value="accepts_chat" id="accepts_chat" class="rounded text-blue-600">
                            <label for="accepts_chat" class="ml-2 text-sm text-gray-700"> Accepts Chat </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="flex items-center justify-between">
                <x-primary-button>
                    {{ __('Create') }}
                </x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
