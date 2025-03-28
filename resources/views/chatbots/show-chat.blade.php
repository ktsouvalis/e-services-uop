<x-app-layout>
    @if($chatbot->aimodel->properties()['accepts_chat'])
    <style>
        .chat-container pre {
            white-space: pre-wrap; /* Ensure code blocks wrap */
            word-wrap: break-word; /* Ensure long words break */
            background-color: #ffffff; /* Light gray background for code blocks */
            padding: 10px; /* Padding for code blocks */
            border-radius: 5px; /* Rounded corners for code blocks */
        }
    
        .chat-container code {
            background-color: #ffffff; /* Light gray background for inline code */
            padding: 2px 4px; /* Padding for inline code */
            border-radius: 3px; /* Rounded corners for inline code */
        }
    
        .chat-container ul {
            list-style-type: disc;
            margin-left: 20px;
        }
    
        .chat-container ol {
            list-style-type: decimal;
            margin-left: 20px;
        }
    
        .chat-container li {
            margin-bottom: 5px;
        }
    
        .chat-container strong {
            font-weight: bold;
        }

        

        .legend-container {
            margin-top: 1rem;
            text-align: center; /* Center the legend */
        }

        .legend-wrapper {
            display: inline-block; /* Make the table compact */
            max-width: 400px; /* Limit the width of the table */
        }

        .legend-table th {
            font-weight: bold;
            text-transform: uppercase;
        }

        .legend-table td {
            font-size: 0.875rem; /* Smaller font size for a legend-like appearance */
        }
    </style>
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        @if($chatbot->aimodel->properties()['accepts_developer_messages'])
            <div class="shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">{{ __('Developer Messages') }}</h3>
                <div class="mt-6">
                    <form action="{{ route('chatbots.store-developer-messages', $chatbot) }}" method="POST">
                        @csrf
                        <textarea name="developer_messages" id="messages" class="w-full border-gray-300 rounded-md shadow-sm" rows="3" placeholder="Comma Seperated Instructions Messages to the model e.g. you are a model that talks like a pirate"></textarea>
                        <x-primary-button id="send-dev-button" class="mt-2">
                            {{ __('Send Developer Messages') }}
                        </x-primary-button>
                    </form>
                    
                </div>
            </div>
        @endif

        @if($chatbot->aimodel->properties()['accepts_system_messages'])
            <div class="shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">{{ __('System Messages') }}</h3>
                <div class="mt-6">
                    <form action="{{ route('chatbots.store-system-messages', $chatbot) }}" method="POST">
                        @csrf
                        <textarea name="system_messages" id="messages" class="w-full border-gray-300 rounded-md shadow-sm" rows="3" placeholder="Comma Seperated Instructions Messages to the model e.g. you are a model that talks like a pirate"></textarea>
                        <x-primary-button id="send-sys-button" class="mt-2">
                            {{ __('Send System Messages') }}
                        </x-primary-button>
                    </form>
                    
                </div>
            </div>
        @endif
        <div class=" shadow-sm sm:rounded-lg p-6">
            <h3 class="text-lg font-semibold mb-4">{{$chatbot->title}}: {{ __('Dialogue') }} with {{$chatbot->aiModel->name}}</h3>
            @if($chatbot->aimodel->properties()['reasoning_effort'])
            <div class="mt-3">
                <label for="reasoning_effort" class="block text-sm font-medium text-gray-700">{{ __('Reasoning Effort') }}</label>
                <select name="reasoning_effort" id="reasoning_effort" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                    <option value="0">Low</option>
                    <option value="1">Medium</option>
                    <option value="2">High</option>
                </select>
            <div>
            @endif
            <div id="chat-container" class="space-y-4 chat-container">
                <!-- Messages will be appended here -->
            </div>   
            <div class="mt-6">
                @if($chatbot->aimodel->name == 'deepseek-chat')
                <div class="mt-3">
                    <label for="temperature" class="text-sm font-medium text-gray-700">{{ __('Temperature') }}</label>
                    <select name="temperature" id="temperature" class="my-3 mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                        <option value="0">0.0</option>
                        <option value="1" selected>1.0</option>
                        <option value="1.3">1.3</option>
                        <option value="1.5">1.5</option>
                    </select>
                <div>
                @endif
                <textarea id="message-input" class="w-full border-gray-300 rounded-md shadow-sm" rows="3" placeholder="Type your message..."></textarea>
                @if($chatbot->aimodel->name == 'deepseek-chat')
                <div class="legend-container mt-4">
                <a href="https://api-docs.deepseek.com/quick_start/parameter_settings" target="_blank"><h5 class="text-sm font-semibold mb-2">Temperature Suggested by DeepSeek</h5></a>
                    <div class="legend-wrapper mx-auto">
                        <table class="legend-table border-collapse border border-gray-300 text-sm">
                            <tbody>
                                <tr>
                                    <td class="border border-gray-300 px-4 py-2">Coding / Math</td>
                                    <td class="border border-gray-300 px-4 py-2">0.0</td>
                                </tr>
                                <tr>
                                    <td class="border border-gray-300 px-4 py-2">Data Cleaning / Data Analysis</td>
                                    <td class="border border-gray-300 px-4 py-2">1.0</td>
                                </tr>
                                <tr>
                                    <td class="border border-gray-300 px-4 py-2">General Conversation / Translation</td>
                                    <td class="border border-gray-300 px-4 py-2">1.3</td>
                                </tr>
                                <tr>
                                    <td class="border border-gray-300 px-4 py-2">Creative Writing / Poetry</td>
                                    <td class="border border-gray-300 px-4 py-2">1.5</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif
                <x-primary-button id="send-button" class="mt-2" style="display: none;">
                    {{ __('Send') }}
                </x-primary-button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script>
        let chatHistory = [];
        document.addEventListener('DOMContentLoaded', async function() {
            // Load chat history into the chat container
            chatHistory = await getHistory();
            if(chatHistory){
                chatHistory.forEach(message => appendMessage(message.role, message.content));
            }
            else{
                appendMessage('assistant', 'Hello! How can I help you today?');
            }
        });

        document.getElementById('send-button').addEventListener('click', sendMessage);
        document.getElementById('message-input').addEventListener('keypress', function(event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                sendMessage();
            }
        });

        async function sendMessage() {
            const messageInput = document.getElementById('message-input');
            const message = messageInput.value.trim();
            if (message === '') return;

            // Append user's message to the chat container
            appendMessage('user', message);
            
            // Clear the input field
            messageInput.value = '';

            // Update the chat history in the frontend
            chatHistory.push({ role: 'user', content: message });

            // Get the reasoning effort value
            const reasoningEffortElement = document.getElementById('reasoning_effort');
            const reasoningEffort = reasoningEffortElement ? reasoningEffortElement.value : null;

            // Get the temperature value
            const temperatureElement = document.getElementById('temperature');
            const temperature = temperatureElement ? temperatureElement.value : null;

            // Append loading message
            const loadingMessageId = appendMessage('assistant', '. . .');

            // Prepare the request payload
            const payload = { history: chatHistory };
            if (reasoningEffort !== null) {
                payload.reasoning_effort = reasoningEffort;
            }
            payload.temperature = temperature;

            // Send AJAX request to update the history
            fetch(`/chatbots/{{ $chatbot->id }}/user-update-history`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(payload),
                timeout: 600
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    // Handle error response from OpenAI
                    replaceMessage(loadingMessageId, `Error: ${data.error}`);
                } else {
                    // Replace loading message with assistant's message
                    replaceMessage(loadingMessageId, data.assistantMessage.content);

                    // Update the history with the assistant's response
                    chatHistory.push({ role: 'assistant', content: data.assistantMessage.content });
                    saveHistory(chatHistory);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                replaceMessage(loadingMessageId, 'An error occurred while communicating with the server.');
            });
        }

        function appendMessage(role, content) {
            if (role === 'developer' || role === 'system') return; // Ignore developer messages

            const chatContainer = document.getElementById('chat-container');
            const messageWrapper = document.createElement('div');
            messageWrapper.classList.add('flex', 'w-full');
            const messageBox = document.createElement('div');
            messageBox.classList.add('p-4', 'rounded-lg', 'shadow-sm', 'w-auto', 'my-2', 'text-left', 'break-words');
            if (role === 'user') {
                messageWrapper.classList.add('justify-end');
                messageBox.classList.add('bg-yellow-100');
            } else if (role === 'assistant') {
                messageWrapper.classList.add('justify-start');
                messageBox.classList.add('bg-gray-200');
            }
            messageBox.innerHTML = marked.parse(content); // Use marked to render markup
            messageWrapper.appendChild(messageBox);
            chatContainer.appendChild(messageWrapper);

            return messageWrapper;
        }

        function replaceMessage(messageWrapper, content) {
            messageWrapper.querySelector('div').innerHTML = marked.parse(content); // Use marked to render markup
        }

        async function getHistory() {
            // This function should return the current chat history
            
            const response = await fetch(`/chatbots/{{ $chatbot->id }}/get-history`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                },
            });
            return response.json();
        }
        

        function saveHistory(history) {
            fetch(`/chatbots/{{ $chatbot->id }}/assistant-update-history`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ history })
            });
        }
    </script>
    @endif
</x-app-layout>