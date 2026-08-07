@push('title')
    <title>Review</title>
@endpush
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Review') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                @include('mailers._steps', ['step' => 3])

                @php
                    $validCount = collect($review_array)->where('to', '!=', 'Department not found')->count();
                    $unmatchedCount = count($review_array) - $validCount;
                @endphp

                <div class="flex flex-wrap gap-3 mb-4 text-sm">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-green-50 text-green-800 border border-green-200">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                        {{ $validCount }} {{ __('matched to a department') }}
                    </span>
                    @if($unmatchedCount > 0)
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-red-50 text-red-800 border border-red-200">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                        </svg>
                        {{ $unmatchedCount }} {{ __('file(s) not matched to a department - skipped') }}
                    </span>
                    @endif
                </div>

                @if($sendBatch)
                    @include('mailers._send-progress', ['mailer' => $mailer, 'sendBatch' => $sendBatch])
                @endif

                <div class="overflow-x-auto">
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
                            @foreach ($review_array as $review)
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
                                        @can('update', $mailer)
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
                    @can('update', $mailer)
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
        </div>
    </div>
</x-app-layout>
