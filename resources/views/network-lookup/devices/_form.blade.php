@php
    $device = $device ?? null;
@endphp

<div class="mb-4">
    <label for="name" class="block text-sm font-medium text-gray-700">{{ __('Name') }}</label>
    <input type="text" name="name" id="name" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" value="{{ old('name', $device->name ?? '') }}" required>
    @error('name')
        <span class="text-red-500 text-sm">{{ $message }}</span>
    @enderror
</div>

<div class="mb-4">
    <label for="mgmt_ip" class="block text-sm font-medium text-gray-700">{{ __('Management IP') }}</label>
    <input type="text" name="mgmt_ip" id="mgmt_ip" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" value="{{ old('mgmt_ip', $device->mgmt_ip ?? '') }}" required>
    @error('mgmt_ip')
        <span class="text-red-500 text-sm">{{ $message }}</span>
    @enderror
</div>

<div class="mb-4">
    <label for="vendor" class="block text-sm font-medium text-gray-700">{{ __('Vendor') }}</label>
    <select name="vendor" id="vendor" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
        @foreach (['huawei' => 'Huawei', 'cisco' => 'Cisco'] as $value => $label)
            <option value="{{ $value }}" {{ old('vendor', $device->vendor ?? 'huawei') === $value ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
    </select>
    @error('vendor')
        <span class="text-red-500 text-sm">{{ $message }}</span>
    @enderror
</div>

<div class="mb-4">
    <label for="role" class="block text-sm font-medium text-gray-700">{{ __('Role') }}</label>
    <select name="role" id="role" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
        <option value="l2" {{ old('role', $device->role ?? 'l2') === 'l2' ? 'selected' : '' }}>{{ __('L2 (MAC table source)') }}</option>
        <option value="core" {{ old('role', $device->role ?? 'l2') === 'core' ? 'selected' : '' }}>{{ __('Core (ARP source)') }}</option>
    </select>
    @error('role')
        <span class="text-red-500 text-sm">{{ $message }}</span>
    @enderror
</div>

<div class="mb-4">
    <label for="protocol" class="block text-sm font-medium text-gray-700">{{ __('Protocol') }}</label>
    <select name="protocol" id="protocol" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
        <option value="ssh" {{ old('protocol', $device->protocol ?? 'ssh') === 'ssh' ? 'selected' : '' }}>SSH</option>
        <option value="telnet" {{ old('protocol', $device->protocol ?? 'ssh') === 'telnet' ? 'selected' : '' }}>Telnet</option>
    </select>
    @error('protocol')
        <span class="text-red-500 text-sm">{{ $message }}</span>
    @enderror
</div>
