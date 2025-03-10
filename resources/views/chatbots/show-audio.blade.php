@php
    $history = json_decode($chatbot->history, true);
@endphp
<x-app-layout>
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="shadow-sm sm:rounded-lg p-6">
           
            @if(!$chatbot->history)
            <h3 class="text-lg font-semibold mb-4">{{ __('Upload Audio File') }}</h3>
                <form action="{{ route('chatbots.submit-audio', $chatbot) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="mt-4">
                        <label for="audio_file" class="block text-sm font-medium text-gray-700">{{ __('Audio File') }}</label>
                        <input required type="file" name="audio_file" id="audio_file" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                    </div>
                    
                    <div class="mt-6">
                        <x-primary-button>
                            {{ __('Upload') }}
                        </x-primary-button>
                    </div>
                </form>
            @else
            <h3 class="text-lg font-semibold mb-4">{{ __('Transcribe Audio File') }}</h3>
                <div class="mt-4">
                    {{json_decode($chatbot->history)->file}}
                    <form action="{{route('chatbots.download-audio', $chatbot)}}" method="GET">
                        @csrf
                        <button type="submit" class="text-blue-500 underline"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m9 13.5 3 3m0 0 3-3m-3 3v-6m1.06-4.19-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z" />
                          </svg>
                          </button>
                    </form>
                    
                </div>
                <form action={{route('chatbots.transcribe-audio', $chatbot)}} method="POST">
                    @csrf
                    <div class="mt-6">
                        <label for="speaker_diarization" class="block text-sm font-medium text-gray-700">{{ __('Speaker Diarization') }}</label>
                        <input type="checkbox" name="speaker_diarization" id="speaker_diarization" class="mt-1 block">
                    </div>
                    <div class="mt-3">
                        <x-primary-button>
                            {{ __('Transcribe') }}
                        </x-primary-button>
                    </div>
                </form>
                @if(json_decode($chatbot->history)->transcription)
                <div class="mt-6">
                    {{json_decode($chatbot->history)->transcription}}
                </div>
                @endif

            @endif

        </div>
    </div>
</x-app-layout>