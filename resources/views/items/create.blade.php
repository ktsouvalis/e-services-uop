@push('title')
    <title>Insert Item</title>
@endpush
@php
    $categories = App\Models\Category::all();
@endphp
<x-app-layout>
     <!-- Section 2: Create Form -->
     <div class="max-w-5xl mx-auto bg-white shadow-sm sm:rounded-lg p-6">
        <h3 class="text-lg font-semibold mb-4">{{ __('Create New Item') }}</h3>
        <form action="{{ route('items.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="flex flex-wrap -mx-3 mb-6">
                <!-- Category Field -->
                <div class="w-full md:w-1/2 px-3 mb-6 md:mb-0">
                    <label for="category_id" class="block text-sm font-medium text-gray-700">{{ __('Category') }}</label>
                    <select name="category_id" id="category_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('category_id')
                        <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Description Field -->
                <div class="w-full md:w-1/2 px-3">
                    <label for="description" class="block text-sm font-medium text-gray-700">{{ __('Description') }}</label>
                    <input type="text" name="description" id="description" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" value="{{ old('description') }}">
                    @error('description')
                        <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="flex flex-wrap -mx-3 mb-6">
                <!-- S/N Field -->
                <div class="w-full md:w-1/2 px-3 mb-6 md:mb-0">
                    <label for="s_n" class="block text-sm font-medium text-gray-700">{{ __('S/N') }}</label>
                    <input type="text" name="s_n" id="s_n" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" value="{{ old('s_n') }}">
                    @error('s_n')
                        <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Brand/Model Field -->
                <div class="w-full md:w-1/2 px-3">
                    <label for="brand_model" class="block text-sm font-medium text-gray-700">{{ __('Brand/Model') }}</label>
                    <input type="text" name="brand_model" id="brand_model" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" value="{{ old('brand_model') }}">
                    @error('brand_model')
                        <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="flex flex-wrap -mx-3 mb-6">
                <!-- Status Field -->
                <div class="w-full md:w-1/2 px-3 mb-6 md:mb-0">
                    <label for="status" class="block text-sm font-medium text-gray-700">{{ __('Status') }}</label>
                    <select name="status" id="status" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                        <option value="Άχρηστο">Άχρηστο</option>
                        <option value="Μέτρια">Μέτρια</option>
                        <option value="Καλή">Καλή</option>
                        <option value="Άριστη">Άριστη</option>
                    </select>
                    @error('status')
                        <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Year of Purchase Field -->
                <div class="w-full md:w-1/2 px-3">
                    <label for="year_of_purchase" class="block text-sm font-medium text-gray-700">{{ __('Year of Purchase') }}</label>
                    <input type="text" name="year_of_purchase" id="year_of_purchase" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" value="{{ old('year_of_purchase') }}">
                    @error('year_of_purchase')
                        <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="flex flex-wrap -mx-3 mb-6">
                <!-- Value Field -->
                <div class="w-full md:w-1/2 px-3 mb-6 md:mb-0">
                    <label for="value" class="block text-sm font-medium text-gray-700">{{ __('Value') }}</label>
                    <input type="text" name="value" id="value" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" value="{{ old('value') }}">
                    @error('value')
                        <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Source of Funding Field -->
                <div class="w-full md:w-1/2 px-3">
                    <label for="source_of_funding" class="block text-sm font-medium text-gray-700">{{ __('Source of Funding') }}</label>
                    <select name="source_of_funding" id="source_of_funding" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                        <option value="Τακτικός Προϋπολογισμός">Τακτικός Προϋπολογισμός</option>
                        <option value="ΠΔΕ">ΠΔΕ</option>
                    </select>
                    @error('source_of_funding')
                        <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="flex flex-wrap -mx-3 mb-6">
                <!-- Comments Field -->
                <div class="w-full md:w-1/2 px-3 mb-6 md:mb-0">
                    <label for="comments" class="block text-sm font-medium text-gray-700">{{ __('Comments') }}</label>
                    <textarea name="comments" id="comments" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">{{ old('comments') }}</textarea>
                    @error('comments')
                        <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Assigned To Field -->
                <div class="w-full md:w-1/2 px-3">
                    <label for="user_id" class="block text-sm font-medium text-gray-700">{{ __('Assigned To') }}</label>
                    <select name="user_id" id="user_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                        <option value="99">-</option>
                        <option value="{{ auth()->user()->id }}">{{ auth()->user()->name }}</option>
                    </select>
                    @error('user_id')
                        <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="flex flex-wrap -mx-3 mb-6">
                <!-- File Upload Field (multiple) -->
                <div class="w-full px-3">
                    <label for="file_path" class="block text-sm font-medium text-gray-700">{{ __('Files') }}</label>
                    <input type="file" name="file_path[]" id="file_path" multiple class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                    @error('file_path')
                        <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                    @error('file_path.*')
                        <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <!-- Submit Button -->
            <div class="flex items-center justify-between">
                <a href="{{ route('items.index') }}" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    {{ __('Επιστροφή') }}
                </a>
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    {{ __('Δημιουργία') }}
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
