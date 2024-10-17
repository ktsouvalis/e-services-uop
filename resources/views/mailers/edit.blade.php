<!-- resources/views/mailers/edit.blade.php -->
@push('title')
    <title>Edit Mailer</title>
@endpush
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Mailer') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">{{ __('Edit Mail to Department') }}</h3>

                <form action="{{ route('mailers.update', $mailer->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <!-- Name Field -->
                    <div class="mb-4">
                        <label for="name" class="block text-sm font-medium text-gray-700">{{ __('Name') }}</label>
                        <input type="text" name="name" id="name" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" value="{{ old('name', $mailer->name) }}">
                        @error('name')
                            <span class="text-red-500 text-sm">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- Subject Field -->
                    <div class="mb-4">
                        <label for="subject" class="block text-sm font-medium text-gray-700">{{ __('Subject') }}</label>
                        <input type="text" name="subject" id="subject" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" value="{{ old('subject', $mailer->subject) }}">
                        @error('subject')
                            <span class="text-red-500 text-sm">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- Body Field -->
                    <div class="mb-4">
                        <label for="body" class="block text-sm font-medium text-gray-700">{{ __('Body') }}</label>
                        {{-- <textarea name="body" id="body" rows="4" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">{{ old('body', $mailer->body) }}</textarea> --}}
                        <textarea name="body" id="myeditorinstance">{{ old('body', $mailer->body) }}</textarea>
                        @error('body')
                            <span class="text-red-500 text-sm">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- Signature Field -->
                    <div class="mb-4">
                        <label for="signature" class="block text-sm font-medium text-gray-700">{{ __('Signature') }}</label>
                        <input type="text" name="signature" id="signature" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" value="{{ old('signature', $mailer->signature) }}">
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
                <form action="{{ route('mailers.upload_files', $mailer->id) }}" method="POST" enctype="multipart/form-data">
                    <!-- Files Field (Multiple File Upload) -->
                    @csrf
                    <div class="mb-4">
                        <label for="files" class="block text-sm font-medium text-gray-700">{{ __('Files') }}</label>
                        <input type="file" name="files[]" id="files" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" multiple required>
                        @error('files.*')
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
                @if ($mailer->files)
                    <p class="mt-2 text-sm text-gray-500">{{ __('Currently uploaded files:') }} {{count($mailer->files)}}</p>
                    <table class="border text-center w-full divide-y divide-gray-200">
                        <tbody>
                            @foreach ($mailer->files as $file)
                                <tr class="border">
                                    <td>{{ $file['filename'] }}</td>
                                    <td class="flex">
                                        <a href="{{ route('mailers.download_file', ['mailer' => $mailer->id, 'index' => $file['index']]) }}" class="text-blue-600">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                            </svg>
                                        </a>
                                        <form action="{{route('mailers.delete_file', ['mailer' => $mailer->id, 'index' => $file['index']])}}" method="post">
                                            @csrf
                                            <button type="submit" class="text-red-600 ml-4" onclick="return confirm('Are you sure?')">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                                </svg>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            <div class="flex items-center justify-end mt-4">
                @if($mailer->files)
                    <form action="{{route('mailers.clean_storage', $mailer->id)}}" method="post">
                        @csrf
                        <button type="submit" class="inline-flex items-center m-2 px-4 py-2 bg-red-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mx-1">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5m6 4.125 2.25 2.25m0 0 2.25 2.25M12 13.875l2.25-2.25M12 13.875l-2.25 2.25M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                            </svg>
                            Delete All Files
                        </button>
                    </form>
                    <a href="{{ route('mailers.review', $mailer->id) }}" target="_blank" class="inline-flex items-center px-4 py-2 bg-blue-300 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-400 focus:bg-blue-400 active:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 transition ease-in-out duration-150">
                        Review
                    </a>
                @endif
            </div>
            </div>
        </div>
</x-app-layout>
