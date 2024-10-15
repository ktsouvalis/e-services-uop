<!-- resources/views/mailers/edit.blade.php -->
@push('title')
    <title>Log Reader</title>
@endpush
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Log Reader') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">{{ __('Upload file and search its contents for specific RegEx') }}</h3>
                <form action="{{ route('log-reader.read-logs') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-4">
                        <label for="file" class="block text-sm font-medium text-gray-700">{{ __('File') }}</label>
                        <input type="file" name="file" id="file" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" multiple required>
                        @error('file')
                            <span class="text-red-500 text-sm">{{ $message }}</span>
                        @enderror
                        
                    </div>
                    <div class="mb-4">
                        <label for="regex" class="block text-sm font-medium text-gray-700">{{ __('Regex') }}</label>
                        <input type="text" name="regex" id="regex" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"  required>
                        @error('regex')
                            <span class="text-red-500 text-sm">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="mb-4">
                        <label for="extra_chars" class="block text-sm font-medium text-gray-700">{{ __('# of extra chars*') }}</label>
                        <input type="number" name="extra_chars" id="extra_chars" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" value=0 required>
                        @error('regex')
                            <span class="text-red-500 text-sm">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="flex items-center justify-end mt-4">
                        <x-primary-button>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                              </svg>
                            {{ __('Submit') }}
                        </x-primary-button>
                    </div>
                </form>
                <div class="mb-4">
                    <table class="my-2 w-full divide-y divide-gray-200 border border-gray-300">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border border-gray-300"><b>Αναζήτηση για</b></th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border border-gray-300"><b>RegEx**</b></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap border border-gray-300">IP</td>
                                <td class="px-6 py-4 whitespace-nowrap border border-gray-300">"\b((25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\b"</td>
                            </tr>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap border border-gray-300">email</td>
                                <td class="px-6 py-4 whitespace-nowrap border border-gray-300">"\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b"</td>
                            </tr>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap border border-gray-300">URL</td>
                                <td class="px-6 py-4 whitespace-nowrap border border-gray-300">"\b(https?|ftp|file):\/\/[-A-Za-z0-9+&@#\/%?=~_|!:,.;]*[-A-Za-z0-9+&@#\/%=~_|]"</td>
                            </tr>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap border border-gray-300">Ημερομηνία</td>
                                <td class="px-6 py-4 whitespace-nowrap border border-gray-300">"\b\d{1,2}\/\d{1,2}\/\d{4}\b"</td>
                            </tr>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap border border-gray-300">Ώρα</td>
                                <td class="px-6 py-4 whitespace-nowrap border border-gray-300">"\b\d{1,2}:\d{1,2}:\d{1,2}\b"</td>
                            </tr>
                        </tbody>
                    </table>
                    <p><small>*Αριθμός έξτρα χαρακτήρων που υπάρχουν στο τέλος του RegEx αλλά δεν θέλουμε να συμπεριληφθούν στο αρχείο</small></p>
                    <p><small>**Copy - Paste χωρίς τα εισαγωγικά</small></p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
