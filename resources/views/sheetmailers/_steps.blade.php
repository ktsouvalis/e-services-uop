@php
    $steps = [
        1 => 'Name',
        2 => 'Compose & recipients',
        3 => 'Confirm & send',
    ];
@endphp
<nav class="mb-4" aria-label="Progress">
    <ol class="flex items-center text-sm">
        @foreach($steps as $number => $label)
            <li class="flex items-center {{ $number < count($steps) ? 'flex-1' : '' }}">
                <span class="flex items-center {{ $number == $step ? 'text-blue-700 font-semibold' : ($number < $step ? 'text-green-700' : 'text-gray-400') }}">
                    <span class="flex items-center justify-center w-6 h-6 rounded-full border text-xs mr-2
                        {{ $number == $step ? 'border-blue-600 bg-blue-50' : ($number < $step ? 'border-green-600 bg-green-50' : 'border-gray-300') }}">
                        @if($number < $step)
                            &#10003;
                        @else
                            {{ $number }}
                        @endif
                    </span>
                    {{ $label }}
                </span>
                @if($number < count($steps))
                    <span class="flex-1 border-t mx-3 {{ $number < $step ? 'border-green-600' : 'border-gray-300' }}"></span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
