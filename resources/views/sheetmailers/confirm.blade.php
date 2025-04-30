@push('title')
    <title>Confirm</title>
@endpush
<x-app-layout>
<div class="container mx-auto px-4">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Confirm') }}
        </h2>
    </x-slot>
    @if(session('non_emails'))
    <div class="bg-red-100 border border-red-400 px-4 py-3 rounded relative" role="alert">
        <strong class="font-bold">Οι παρακάτω διευθύνσεις δεν είναι έγκυρες</strong>
        <br>
        <table class="my-2 w-full divide-y divide-gray-200 border border-gray-300">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border border-gray-300">#</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border border-gray-300">Invalid Email</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @foreach(session('non_emails') as $non_email)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap border border-gray-300">{{ $loop->iteration }}</td>
                        <td class="px-6 py-4 whitespace-nowrap border border-gray-300">{{ $non_email }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
    @if(session('emailCount') > 0)
    <div class="mt-4">
        <form action="{{ route('sheetmailers.send', $sheetmailer) }}" method="POST">
            @csrf
            <a href="{{ route('sheetmailers.preview', $sheetmailer) }}" target="_blank" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 focus:bg-blue-500 active:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150 mr-2">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mx-1">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Preview Mail
            </a>
            <button type="submit" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-500 focus:bg-green-500 active:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mx-1">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                  </svg>
                  Send All Emails
            </button>
        </form>
    </div>
    @endif
    <div class="mt-4">
        <a href="{{ route("sheetmailers.edit", $sheetmailer) }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-500 focus:bg-gray-500 active:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mx-1">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
            Go Back
        </a>
    </div>
    <div class="py-12 overflow-x-auto">
        <div class="bg-green-100 border border-green-400 px-4 py-3 rounded relative" role="alert">
            <strong class="font-bold">Έγκυρες διευθύνσεις email ({{ session('emailCount')}})</strong>
            <br>
            <table class="my-2 w-full divide-y divide-gray-200 border border-gray-300">
                <thead class="bg-gray-50">
                    <tr>
                        <th style="width: auto;" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border border-gray-300"><b>#</b></th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border border-gray-300"><b>email</b></th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border border-gray-300"><b>Additional Data</b></th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach (session('emails',[]) as $correspondent)
                    <tr>
                        <td style="width: auto;" class="px-6 py-4 whitespace-nowrap border border-gray-300">{{ $loop->iteration }}</td>
                        <td class="px-6 py-4 whitespace-nowrap border border-gray-300">{{ $correspondent['email'] }}</td>
                        <td class="px-6 py-4 border border-gray-300">{{ $correspondent['additionalData'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    
</div>
</x-app-layout>