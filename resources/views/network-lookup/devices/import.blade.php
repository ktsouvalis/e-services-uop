@push('title')
    <title>Import Network Devices</title>
@endpush
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Import Network Devices') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <p class="text-sm text-gray-600 mb-4">
                    {{ __('Upload a JSON file listing devices to add or update in bulk. Each entry needs: name, ip, vendor (huawei/cisco), role (l2/core), and optionally protocol (ssh/telnet, defaults to ssh). A device that already exists (matched by name) is updated in place; a new name is added. Existing devices missing from the file are left untouched.') }}
                </p>

                <pre class="bg-gray-50 border border-gray-300 rounded-md p-3 text-xs mb-6 overflow-x-auto">[
    {"name": "Example_SW_1", "ip": "10.23.255.10", "vendor": "huawei", "role": "l2", "protocol": "ssh"},
    {"name": "Example_SW_2", "ip": "10.23.255.11", "vendor": "cisco", "role": "l2", "protocol": "telnet"}
]</pre>

                <form action="{{ route('network-lookup.devices.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-4">
                        <label for="file" class="block text-sm font-medium text-gray-700">{{ __('Devices JSON file') }}</label>
                        <input type="file" name="file" id="file" accept="application/json" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                        @error('file')
                            <span class="text-red-500 text-sm">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="flex items-center justify-end mt-4">
                        <x-primary-button>{{ __('Import') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
