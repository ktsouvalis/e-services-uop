<!-- resources/views/items/index.blade.php -->
@push('title')
    <title>Items</title>
@endpush

@php
    $categories = App\Models\Category::all();
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight flex flex-x">
            {{ __('Items') }}
            <a href="{{ route('items.create') }}" class="mx-3 bg-blue-700 hover:bg-blue-900 text-white font-bold py-2 px-4 rounded" title="Προσθήκη">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-7">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                  </svg>
            </a>
            <a href="{{route('items.extract')}}" class="mx-3 bg-green-700 hover:bg-green-900 text-white font-bold py-2 px-4 rounded" title="Εξαγωγή">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m.75 12 3 3m0 0 3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                </svg>
            </a>
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Section 1: List of Items -->
            <div class="bg-white shadow-sm sm:rounded-lg p-6 overflow-x-auto">


                <h3 class="text-lg font-semibold mb-4">{{ __('Items List') }}</h3>

                @if($items->isEmpty())
                    <p>{{ __('No entries found.') }}</p>
                @else
                    <table id="DataTable" class="text-center w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                <th class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider" id="search">Κατηγορία</th>
                                <th class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Περιγραφήn</th>
                                <th class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">S/N</th>
                                <th class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Μάρκα/Μοντέλο</th>
                                <th class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider" id="search">Φυσική Κατάσταση</th>
                                <th class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider" id="search">Έτος κτήσης</th>
                                <th class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Αξία</th>
                                <th class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider" id="search">Πηγή Χρηματοδότησης</th>
                                <th class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Σχόλια</th>
                                <th class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider" id="search">Χρέωση</th>
                                <th class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Χρεωστικό</th>
                                <th class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Ενέργειες</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($items as $item)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $item->id }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $item->category->name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $item->description }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $item->s_n }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $item->brand_model }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $item->status }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $item->year_of_purchase }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $item->value }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $item->source_of_funding }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $item->comments }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ optional($item->user)->name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @can('view', $item)
                                            <a href="{{ route('items.download_file', ['item' => $item->id]) }}" class="text-blue-600">
                                                {{ $item->file_path }}
                                            </a>
                                        @else
                                            {{ $item->file_path }}
                                        @endcan
                                    <td class="px-6 py-4 flex justify-center">
                                        @can('update', $item)
                                        <a href="{{ route('items.edit', $item->id) }}" class="text-blue-600">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.2" stroke="currentColor" class="w-6 h-6">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                            </svg>
                                        </a>
                                        <form action="{{ route('items.destroy', $item->id) }}" method="POST" class="inline-block">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 ml-4" onclick="return confirm('Are you sure?')">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                </svg>
                                            </button>
                                        </form>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
