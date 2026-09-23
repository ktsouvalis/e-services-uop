<div class="bg-white shadow-sm sm:rounded-lg p-6">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold">{{ __('Newt agents') }}</h3>
        <a href="{{ route('pangolin.newt-agents.create') }}" class="text-blue-600 hover:underline text-sm">{{ __('Add agent') }}</a>
    </div>
    <p class="text-sm text-gray-500 mb-4">
        {{ __('SSH pull targets for the Connections tab\'s "Fetch now" — each agent\'s docker logs newt output is parsed for ACCESS sessions.') }}
    </p>

    <table class="w-full table-auto divide-y divide-gray-200 border border-gray-300 text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-2 text-left border border-gray-300">{{ __('Name') }}</th>
                <th class="px-4 py-2 text-left border border-gray-300">{{ __('IP address') }}</th>
                <th class="px-4 py-2 text-left border border-gray-300">{{ __('Actions') }}</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            @forelse ($newtAgents as $agent)
                <tr>
                    <td class="px-4 py-2 border border-gray-300">{{ $agent->name }}</td>
                    <td class="px-4 py-2 border border-gray-300">{{ $agent->ip }}</td>
                    <td class="px-4 py-2 border border-gray-300 whitespace-nowrap">
                        <a href="{{ route('pangolin.newt-agents.edit', $agent) }}" class="text-blue-600 inline-block align-middle" title="{{ __('Edit') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                            </svg>
                        </a>
                        <form action="{{ route('pangolin.newt-agents.destroy', $agent) }}" method="POST" class="inline-block ml-2 align-middle" onsubmit="return confirm('Remove {{ $agent->name }}? This cannot be undone.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600" title="{{ __('Delete') }}">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="px-3 py-6 text-center text-gray-500">{{ __('No Newt agents yet.') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
