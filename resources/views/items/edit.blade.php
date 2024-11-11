<!-- resources/views/items/edit.blade.php -->
@push('title')
    <title>Edit Item</title>
@endpush
@php
    $categories = App\Models\Category::all();
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Item') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <form action="{{ route('items.update', $item->id) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <div class="flex flex-wrap -mx-3 mb-6">
                            <div class="w-full md:w-1/2 px-3 mb-6 md:mb-0">
                                <label for="category" class="block text-gray-700 text-sm font-bold mb-2">{{ __('Category') }}</label>
                                <select name="category_id" id="category" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}" {{ $item->category_id == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="w-full md:w-1/2 px-3">
                                <label for="description" class="block text-gray-700 text-sm font-bold mb-2">{{ __('Description') }}</label>
                                <input type="text" name="description" id="description" value="{{ $item->description }}" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            </div>
                        </div>

                        <div class="flex flex-wrap -mx-3 mb-6">
                            <div class="w-full md:w-1/2 px-3 mb-6 md:mb-0">
                                <label for="s_n" class="block text-gray-700 text-sm font-bold mb-2">{{ __('S/N') }}</label>
                                <input type="text" name="s_n" id="s_n" value="{{ $item->s_n }}" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            </div>
                            <div class="w-full md:w-1/2 px-3">
                                <label for="brand_model" class="block text-gray-700 text-sm font-bold mb-2">{{ __('Brand/Model') }}</label>
                                <input type="text" name="brand_model" id="brand_model" value="{{ $item->brand_model }}" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            </div>
                        </div>

                        <div class="flex flex-wrap -mx-3 mb-6">
                            <div class="w-full md:w-1/2 px-3 mb-6 md:mb-0">
                                <label for="status" class="block text-gray-700 text-sm font-bold mb-2">{{ __('Status') }}</label>
                                <select name="status" id="status" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                    <option value="Άχρηστο" {{ $item->status == "Άχρηστο" ? 'selected' : '' }}>Άχρηστο</option>   
                                    <option value="Μέτρια" {{ $item->status == "Μέτρια" ? 'selected' : '' }}>Μέτρια</option>
                                    <option value="Καλή" {{ $item->status == "Καλή" ? 'selected' : '' }}>Καλή</option>
                                    <option value="Άριστη" {{ $item->status == "Άριστη" ? 'selected' : '' }}>Άριστη</option>
                                </select>
                            </div>
                            <div class="w-full md:w-1/2 px-3">
                                <label for="year_of_purchase" class="block text-gray-700 text-sm font-bold mb-2">{{ __('Year of Purchase') }}</label>
                                <input type="text" name="year_of_purchase" id="year_of_purchase" value="{{ $item->year_of_purchase }}" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            </div>
                        </div>

                        <div class="flex flex-wrap -mx-3 mb-6">
                            <div class="w-full md:w-1/2 px-3 mb-6 md:mb-0">
                                <label for="value" class="block text-gray-700 text-sm font-bold mb-2">{{ __('Value') }}</label>
                                <input type="text" name="value" id="value" value="{{ $item->value }}" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            </div>
                            <div class="w-full md:w-1/2 px-3">
                                <label for="source_of_funding" class="block text-gray-700 text-sm font-bold mb-2">{{ __('Source of Funding') }}</label>
                                <select name="source_of_funding" id="source_of_funding" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                    <option value="Τακτικός Προϋπολογισμός" {{ $item->source_of_funding == "Τακτικός Προϋπολογισμός" ? 'selected' : '' }}>Τακτικός Προϋπολογισμός</option>
                                    <option value="ΠΔΕ" {{ $item->source_of_funding == "ΠΔΕ" ? 'selected' : '' }}>ΠΔΕ</option>      
                                </select>
                            </div>
                        </div>

                        <div class="flex flex-wrap -mx-3 mb-6">
                            <div class="w-full md:w-1/2 px-3 mb-6 md:mb-0">
                                <label for="comments" class="block text-gray-700 text-sm font-bold mb-2">{{ __('Comments') }}</label>
                                <input type="text" name="comments" id="comments" value="{{ $item->comments }}" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            </div>
                            <div class="w-full md:w-1/2 px-3">
                                <label for="user_id" class="block text-gray-700 text-sm font-bold mb-2">{{ __('Assigned To') }}</label>
                                <select name="user_id" id="user_id" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                    <option value="99" {{ $item->user_id == null ? 'selected' : '' }}>-</option>
                                    <option value="{{ auth()->user()->id }}" {{ $item->user_id == auth()->user()->id ? 'selected' : '' }}>{{ auth()->user()->name }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex flex-wrap -mx-3 mb-6">
                            <div class="w-full px-3">
                                Existing File: <a href="{{ route('items.download_file', ['item' => $item->id]) }}" class="text-blue-600">
                                    {{$item->file_path}}
                                </a>  
                                <label for="file_path" class="block text-gray-700 text-sm font-bold mb-2">{{ __('File Path') }}</label>
                                <input type="file" name="file_path" id="file_path" value="{{ $item->file_path }}" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            </div>
                        </div>

                        <div class="flex items-center justify-between">
                            <a href="{{ route('items.index') }}" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                                {{ __('Cancel') }}
                            </a>
                            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                                {{ __('Update Item') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>