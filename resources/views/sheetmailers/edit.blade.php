<!-- resources/views/mailers/edit.blade.php -->
@push('title')
    <title>Edit Sheetmailer</title>
@endpush
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Sheetmailer') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">{{ __('Edit Sheetmailer') }}</h3>

                <form id="sheetmailer-form" action="{{ route('sheetmailers.update', $sheetmailer->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <!-- Name Field -->
                    <div class="mb-4">
                        <label for="name" class="block text-sm font-medium text-gray-700">{{ __('Name') }}</label>
                        <input type="text" name="name" id="name" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" value="{{ old('name', $sheetmailer->name) }}">
                        @error('name')
                            <span class="text-red-500 text-sm">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- Subject Field -->
                    <div class="mb-4">
                        <label for="subject" class="block text-sm font-medium text-gray-700">{{ __('Subject') }}</label>
                        <input type="text" name="subject" id="subject" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" value="{{ old('subject', $sheetmailer->subject) }}">
                        @error('subject')
                            <span class="text-red-500 text-sm">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- Body Field -->
                    <div class="mb-4">
                        <label for="body" class="block text-sm font-medium text-gray-700">{{ __('Body') }}</label>                   
                        <textarea name="body" id="body" rows="4" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">{{ old('body', $sheetmailer->body) }}</textarea>
                        @error('body')
                            <span class="text-red-500 text-sm">{{ $message }}</span>
                        @enderror
                    </div>
                    
                    <!-- Signature Field -->
                    <div class="mb-4">
                        <label for="signature" class="block text-sm font-medium text-gray-700">{{ __('Signature') }}</label>
                        <input type="text" name="signature" id="signature" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" value="{{ old('signature', $sheetmailer->signature) }}">
                        @error('signature')
                            <span class="text-red-500 text-sm">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="flex items-center justify-end mt-4">
                        <x-primary-button>
                            {{ __('Update') }}
                        </x-primary-button>
                    </div>
                </form>
                <hr class=my-2>
                <form action="{{ route('sheetmailers.upload_file', $sheetmailer->id) }}" method="POST" enctype="multipart/form-data">
                    <!-- Files Field (Multiple File Upload) -->
                    @csrf
                    <div class="mb-4">
                        <label for="file" class="block text-sm font-medium text-gray-700">{{ __('File') }}</label>
                        <input type="file" name="file" id="file" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                        @error('file')
                            <span class="text-red-500 text-sm">{{ $message }}</span>
                        @enderror
                        <div class="flex items-center justify-end mt-4">
                            <x-primary-button>
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mx-1">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 8.25H7.5a2.25 2.25 0 0 0-2.25 2.25v9a2.25 2.25 0 0 0 2.25 2.25h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25H15m0-3-3-3m0 0-3 3m3-3V15" />
                                  </svg>

                                {{ __('Upload') }}
                            </x-primary-button>
                        </div>
                    </div>
                </form>
              </div>
            </div>
        </div>
</x-app-layout>
