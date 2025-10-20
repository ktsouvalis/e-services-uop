<!-- resources/views/mailers/edit.blade.php -->
@push('title')
    <title>Edit Sheetmailer</title>
@endpush
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Sheetmailer') }}
        </h2>
        <div class="text-sm text-gray-600">{{ __('Creator:') }} {{ optional($sheetmailer->user)->name ?? '-' }}</div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-2">{{ __('Edit Sheetmailer') }}</h3>
                <p class="text-xs text-gray-500 mb-4">{{ __('Creator:') }} {{ optional($sheetmailer->user)->name ?? '-' }}</p>

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

                    <!-- Visibility Toggle (creator only) -->
                    <!-- Visibility: only creator can change -->
                    @if(auth()->id() === $sheetmailer->user_id)
                        <div class="mb-4">
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="is_public" value="1" class="rounded" {{ old('is_public', $sheetmailer->is_public) ? 'checked' : '' }}>
                                <span class="ml-2">Public (everyone can view/update)</span>
                            </label>
                        </div>
                    @else
                        <div class="mb-4 text-sm text-gray-600">
                            <span>Visibility: {{ $sheetmailer->is_public ? 'Public' : 'Private' }} (only the creator can change this)</span>
                        </div>
                    @endif

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
                      <textarea name="body" id="myeditorinstance">{{ old('body', $sheetmailer->body) }}</textarea>
                      @error('body')
                          <span class="text-red-500 text-sm">{{ $message }}</span>
                      @enderror
                        {{-- <label for="body" class="block text-sm font-medium text-gray-700">{{ __('Body') }}</label>                   
                        <textarea name="body" id="body" rows="4" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">{{ old('body', $sheetmailer->body) }}</textarea>
                        @error('body')
                            <span class="text-red-500 text-sm">{{ $message }}</span>
                        @enderror --}}
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
                          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mr-2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                          </svg>                          
                          {{ __('Update') }}
                        </x-primary-button>
                    </div>
                </form>
                <hr class=my-2>
                <form action="{{ route('sheetmailers.upload_file', $sheetmailer->id) }}" method="POST" enctype="multipart/form-data">
                    <!-- Files Field (Single File Upload) -->
                    @csrf
                    <div class="mb-4">
                        <label for="file" class="block text-sm font-medium text-gray-700">{{ __('You can upload one file') }}</label>
                        <input type="file" name="file" id="file" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                        @error('file')
                            <span class="text-red-500 text-sm">{{ $message }}</span>
                        @enderror
                        <div class="flex items-center justify-end mt-4">
                            <x-primary-button>
                               {{ __('Next') }}
                               <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 ml-2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 8.689c0-.864.933-1.406 1.683-.977l7.108 4.061a1.125 1.125 0 0 1 0 1.954l-7.108 4.061A1.125 1.125 0 0 1 3 16.811V8.69ZM12.75 8.689c0-.864.933-1.406 1.683-.977l7.108 4.061a1.125 1.125 0 0 1 0 1.954l-7.108 4.061a1.125 1.125 0 0 1-1.683-.977V8.69Z" />
                              </svg>
                              
                            </x-primary-button>
                        </div>
                    </div>
                </form>
                <hr class=my-2>
                <form action="{{ route('sheetmailers.comma_mails', $sheetmailer->id) }}" method="POST" enctype="multipart/form-data">
                    <!-- comma separated emails Field -->
                    @csrf
                    <div class="mb-4">
                        <label for="comma_mails" class="block text-sm font-medium text-gray-700">{{ __('OR you can write comma separated emails') }}</label>
                        <textarea name="comma_mails" id="comma_mails" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required></textarea>
                        @error('file')
                            <span class="text-red-500 text-sm">{{ $message }}</span>
                        @enderror
                        <div class="flex items-center justify-end mt-4">
                            <x-primary-button>
                                {{ __('Next') }}
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 ml-2">
                                  <path stroke-linecap="round" stroke-linejoin="round" d="M3 8.689c0-.864.933-1.406 1.683-.977l7.108 4.061a1.125 1.125 0 0 1 0 1.954l-7.108 4.061A1.125 1.125 0 0 1 3 16.811V8.69ZM12.75 8.689c0-.864.933-1.406 1.683-.977l7.108 4.061a1.125 1.125 0 0 1 0 1.954l-7.108 4.061a1.125 1.125 0 0 1-1.683-.977V8.69Z" />
                                </svg>
                            </x-primary-button>
                        </div>
                    </div>
                </form>
              </div>
            </div>
        </div>
</x-app-layout>
