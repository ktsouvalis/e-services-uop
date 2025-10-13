<x-app-layout>
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <!-- Section 1: List of Chatbots -->
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <h3 class="text-lg font-semibold mb-4">{{ __('Chatbots List') }}</h3>
            <table class="text-center w-full divide-y divide-gray-200">
                <thead>
                    <tr>
                        <th class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                        <th class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                        <th class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">AI Model</th>
                        <th class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($chatbots as $chatbot)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">{{ $chatbot->id }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">{{ $chatbot->title }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">{{ $chatbot->aiModel->name }}</td>
                            <td class="px-6 py-4 flex justify-center">
                                <a href="{{ route('chatbots.show', $chatbot->id) }}" class="text-green-600 ml-4">
                                    <svg 
                                        xmlns="http://www.w3.org/2000/svg" 
                                        fill="none" 
                                        viewBox="0 0 24 24" 
                                        stroke-width="1.2" 
                                        stroke="currentColor" 
                                        class="w-6 h-6">
                                        <path 
                                            stroke-linecap="round"
                                            stroke-linejoin="round" 
                                            d="M12 4.5v15m7.5-7.5h-15" 
                                        />
                                    </svg>
                                </a>
                                <form action="{{ route('chatbots.destroy', $chatbot->id) }}" method="POST" class="inline-block ml-4">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600" onclick="return confirm('Are you sure?')">
                                        <svg 
                                            xmlns="http://www.w3.org/2000/svg" 
                                            fill="none" 
                                            viewBox="0 0 24 24" 
                                            stroke-width="1.5" 
                                            stroke="currentColor" 
                                            class="w-6 h-6">
                                            <path 
                                                stroke-linecap="round" 
                                                stroke-linejoin="round" 
                                                d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" 
                                            />
                                        </svg>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <hr class="mt-6 mb-6">
    <!-- Section 2: Create Form -->
    <div class="max-w-5xl mx-auto bg-white shadow-sm sm:rounded-lg p-6">
        <h3 class="text-lg font-semibold mb-4">{{ __('Create New Chatbot') }}</h3>
        <form action="{{ route('chatbots.store') }}" method="POST" class="mb-3">
            @csrf
            <div class="flex flex-wrap -mx-3 mb-6">
                <!-- Title Field -->
                <div class="w-full md:w-1/2 px-3 mb-6 md:mb-0">
                    <label for="title" class="block text-sm font-medium text-gray-700">{{ __('Title') }}</label>
                    <input type="text" name="title" id="title" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" value="{{ old('title') }}">
                    @error('title')
                        <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>
                <!-- API Key Field -->
                <div class="w-full md:w-1/2 px-3">
                    <label for="api_key" class="block text-sm font-medium text-gray-700">{{ __('API Key') }}*</label>
                    <input type="text" name="api_key" id="api_key" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" value="{{ old('api_key') }}">
                    <span class="text-black-500 text-sm">Not Required for local models</span>
                    @error('api_key')
                        <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>
            </div>
            @php
                $ai_models = App\Models\AImodel::all()->sortBy('name');
            @endphp
            <div class="flex flex-wrap -mx-3 mb-6">
                <!-- AI Model Field -->
                <div class="w-full px-3">
                    <label for="ai_model_id" class="block text-sm font-medium text-gray-700">{{ __('AI Model') }}**</label>
                    <select name="ai_model_id" id="ai_model_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                        @foreach ($ai_models as $model)
                            <option value="{{ $model->id }}" title="{{$model->description}}">{{ $model->name }} ({{$model->source}})</option>
                        @endforeach
                    </select>
                    @error('ai_model_id')
                        <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>
            </div>
            <!-- Submit Button -->
            <div class="flex items-center justify-between">
                <x-primary-button>
                    {{ __('Create') }}
                </x-primary-button>
            </div>
        </form>
        <div class="text-xs">
            *Πρέπει να έχετε δημιουργήσει API Keys στο OpenAI ή στο DeepSeek για να χρησιμοποιήσετε τα μοντέλα τους. <br>
            **Αν δεν βλέπετε το μοντέλο που θέλετε, επικοινωνήστε με τον διαχειριστή.
        </div>
    </div>
</x-app-layout>
