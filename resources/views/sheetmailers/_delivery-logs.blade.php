@if(count($deliveryLogs))
    <div class="mb-6">
        <h3 class="text-sm font-medium text-gray-700 mb-2">{{ __('Delivery logs') }}</h3>
        <table class="border text-sm w-full table-fixed divide-y divide-gray-200">
            <tbody>
                @foreach($deliveryLogs as $log)
                    <tr class="border">
                        <td class="px-2 py-1 break-all w-1/2">{{ $log['filename'] }}</td>
                        <td class="px-2 py-1 text-gray-500 whitespace-nowrap w-1/6">{{ $log['modified_at']->diffForHumans() }}</td>
                        <td class="px-2 py-1 text-gray-500 whitespace-nowrap w-1/6">{{ number_format($log['size'] / 1024, 1) }} KB</td>
                        <td class="px-2 py-1 text-right w-1/6">
                            <a href="{{ route('sheetmailers.download-log', ['sheetmailer' => $sheetmailer->id, 'filename' => $log['filename']]) }}" class="text-blue-600">
                                {{ __('Download') }}
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
