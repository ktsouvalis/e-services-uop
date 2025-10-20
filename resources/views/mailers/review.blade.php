@push('title')
    <title>Review</title>
@endpush
<x-app-layout>
<div class="container mx-auto px-4">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Review') }}
        </h2>
    </x-slot>
   
    
    <div class="py-12 overflow-x-auto">
        <table class="min-w-full bg-white border border-gray-200 text-center">
            <thead>
                <tr>
                    <th class="px-4 py-2 border-b">ID</th>
                    <th class="px-4 py-2 border-b">Name</th>
                    <th class="px-4 py-2 border-b">Email</th>
                    <th class="px-4 py-2 border-b">Αρχείο</th>
                    <th class="px-4 py-2 border-b">Αποστολή email</th>
                </tr>
            </thead>
            <tbody>
                @foreach (session('review_array',[]) as $review)
                    <tr>
                        @if(is_string($review['to']))
                            <td class="px-4 py-2 border-b">{{ $review['to'] }}</td>
                            <td class="px-4 py-2 border-b">-</td>
                            <td class="px-4 py-2 border-b">-</td>
                            <td class="px-4 py-2 border-b">{{ $review['filename'] }}</td>
                            <td class="px-4 py-2 border-b">-</td>
                        @else
                        <td class="px-4 py-2 border-b">{{ $review['to']->id }}</td>
                        <td class="px-4 py-2 border-b">{{ $review['to']->name }}</td>
                        <td class="px-4 py-2 border-b">{{ $review['to']->email }}</td>
                        <td class="px-4 py-2 border-b">{{ $review['filename'] }}</td>
                        <td class="px-4 py-2 border-b">
                            @can('view', $mailer)
                            <form action="{{ route('mailers.send', ['mailer'=>$mailer, 'index' => $review['index'], 'department' => $review['to']]) }}" method="POST">    
                                @csrf
                                <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 focus:bg-blue-500 active:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                                      </svg>
                                      
                                </button>
                            </form>
                            @endcan
                        </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        @can('view', $mailer)
        <form action="{{ route('mailers.send_all', $mailer) }}" method="POST">
            @csrf
            <button type="submit" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-500 focus:bg-green-500 active:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mx-1">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                  </svg>
                  Send All Emails
            </button>
        </form>
        @endcan
    </div>
</div>
</x-app-layout>