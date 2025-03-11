@push('title')
    <title>Edit AI Model</title>
@endpush
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit AI Model') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">{{ __('Edit AI Model') }}</h3>

                <form id="aimodel-form" action="{{ route('aimodels.update', $aimodel->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <!-- Name Field -->
                    <div class="mb-4">
                        <label for="name" class="block text-sm font-medium text-gray-700">{{ __('Name') }}</label>
                        <input type="text" name="name" id="name" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" value="{{ old('name', $aimodel->name) }}">
                        @error('name')
                            <span class="text-red-500 text-sm">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- Description Field -->
                    <div class="mb-4">
                        <label for="description" class="block text-sm font-medium text-gray-700">{{ __('Description') }}</label>
                        <textarea name="description" id="description" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">{{ old('description', $aimodel->description) }}</textarea>
                        @error('description')
                            <span class="text-red-500 text-sm">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- Properties checkboxes -->
                    <div class="mb-4">
                        <label for="properties" class="text-sm font-medium text-gray-700">{{ __('Properties') }}</label>
                        <div class="mt-1 grid grid-cols-1 gap-y-4">
                            <div class="flex items-start">
                                <input type="checkbox" name="properties[]" value="accepts_developer_messages" id="accepts_developer_messages" class="rounded text-blue-600" {{ $aimodel->accepts_developer_messages ? 'checked' : '' }}>
                                <label for="accepts_developer_messages" class="ml-2 text-sm text-gray-700"> Accepts Developer Messages </label>
                            </div>
                            <div class="flex items-start">
                                <input type="checkbox" name="properties[]" value="accepts_system_messages" id="accepts_system_messages" class="rounded text-blue-600" {{ $aimodel->accepts_system_messages ? 'checked' : '' }}>
                                <label for="accepts_system_messages" class="ml-2 text-sm text-gray-700"> Accepts System Messages </label>
                            </div>
                            <div class="flex items-start">
                                <input type="checkbox" name="properties[]" value="reasoning_effort" id="reasoning_effort" class="rounded text-blue-600" {{ $aimodel->reasoning_effort ? 'checked' : '' }}>
                                <label for="reasoning_effort" class="ml-2 text-sm text-gray-700"> Accepts Reasoning Effort </label>
                            </div>
                            <div class="flex items-start">
                                <input type="checkbox" name="properties[]" value="accepts_audio" id="accepts_audio" class="rounded text-blue-600" {{ $aimodel->accepts_audio ? 'checked' : '' }}>
                                <label for="accepts_audio" class="ml-2 text-sm text-gray-700"> Accepts Audio </label>
                            </div>
                            <div class="flex items-start">
                                <input type="checkbox" name="properties[]" value="accepts_chat" id="accepts_chat" class="rounded text-blue-600" {{ $aimodel->accepts_chat ? 'checked' : '' }}>
                                <label for="accepts_chat" class="ml-2 text-sm text-gray-700"> Accepts Chat </label>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <a href="{{ route('aimodels.index') }}" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                            {{ __('Back') }}
                        </a>
                        <a href="{{route('aimodels.edit', $aimodel->id)}}" class="bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                            {{ __('Undo Changes') }}
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
