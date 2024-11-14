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
    @if($item->given_away)
    <div id="item-given-message" class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative text-center" role="alert">
        <span class="block sm:inline">Το αντικείμενο έχει δοθεί εκτός ΜΨΔ</span>
    </div>
    @endif
    @include('components.message')
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
                                <label for="file_path" class="block text-gray-700 text-sm font-bold mb-2">{{ __('File Path') }}</label>
                                <input type="file" name="file_path" id="file_path" value="{{ $item->file_path }}" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            </div>
                        </div>

                        <div class="flex items-center justify-between">
                            <a href="{{ route('items.index') }}" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                                {{ __('Επιστροφή') }}
                            </a>
                            <a href="{{route('items.edit', $item->id)}}" class="bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                                {{ __('Αναίρεση αλλαγών') }}
                            </a>
                            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                                {{ __('Ενημέρωση') }}
                            </button>
                        </div>
                    </form>
                    <hr class="my-3">
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center">
                            <input type="checkbox" id="given" class="given-checkbox" data-item-id="{{ $item->id }}" data-given-url="{{ route('items.given', ['item' => $item->id]) }}" {{ $item->given_away ? 'checked' : '' }}>
                            <label for="given" class="ml-2"> Δόθηκε εκτός ΜΨΔ </label>
                        </div>
                        @if($item->file_path)
                        <div class="flex">
                            <form class="mr-3" action="{{route('items.delete_file', ["item"=> $item->id])}}" method="post">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                      </svg>
                                          
                                </button>
                            </form>
                            
                            <a href="{{ route('items.download_file', ['item' => $item->id]) }}" class="text-blue-600">
                                {{$item->file_path}}
                            </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
