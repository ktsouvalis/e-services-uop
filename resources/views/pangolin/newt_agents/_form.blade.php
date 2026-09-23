@php
    $agent = $agent ?? null;
@endphp

<div class="mb-4">
    <label for="name" class="block text-sm font-medium text-gray-700">{{ __('Name') }}</label>
    <input type="text" name="name" id="name" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" value="{{ old('name', $agent->name ?? '') }}" required>
    @error('name')
        <span class="text-red-500 text-sm">{{ $message }}</span>
    @enderror
</div>

<div class="mb-4">
    <label for="ip" class="block text-sm font-medium text-gray-700">{{ __('IP address') }}</label>
    <input type="text" name="ip" id="ip" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" value="{{ old('ip', $agent->ip ?? '') }}" required>
    @error('ip')
        <span class="text-red-500 text-sm">{{ $message }}</span>
    @enderror
</div>
