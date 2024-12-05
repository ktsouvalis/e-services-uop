@push('scripts')

<script>
    function fetchMenus() {
        const token = document.getElementById('token').innerText.trim();
        fetch('/api/menus', {
            method: 'GET',
            headers: {
                'Authorization': 'Bearer ' + token,
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            console.log(data);
            // Handle the response data here
            alert(JSON.stringify(data));
        })
        .catch(error => console.error('Error:', error));
    }
</script>
@endpush
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    {{ __("You're logged in!") }}
                </div>
                <div>
                    {{-- @isset($token)
                        {{$token}}
                    @endisset --}}
                    @isset($token)
                        <div id="token" style="display: block;">{{$token}}</div>
                        <button onclick="fetchMenus()">Fetch Menus</button>
                    @endisset
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
