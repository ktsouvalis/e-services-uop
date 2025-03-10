@php
    $history = json_decode($chatbot->history, true);
@endphp
<x-app-layout>
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
    </style>
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <h3 class="text-lg font-semibold mb-4">{{$chatbot->title}}: {{ __('Dialogue') }} with {{$chatbot->aiModel->name}}</h3>
            <div id="chat-container" class="space-y-4 chat-container">
                <!-- Messages will be appended here -->
            </div>
            <div class="mt-6">
                <textarea id="message-input" class="w-full border-gray-300 rounded-md shadow-sm" rows="3" placeholder="Type your message..."></textarea>
                <x-primary-button id="send-button" class="mt-2">
                    {{ __('Send') }}
                </x-primary-button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Load chat history into the chat container
            const history = @json($history) || [];
            history.forEach(message => appendMessage(message.role, message.content));
        });

        document.getElementById('send-button').addEventListener('click', sendMessage);
        document.getElementById('message-input').addEventListener('keypress', function(event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                sendMessage();
            }
        });

        function sendMessage() {
            const messageInput = document.getElementById('message-input');
            const message = messageInput.value.trim();
            if (message === '') return;

            // Append user's message to the chat container
            appendMessage('user', message);
            
            // Clear the input field
            messageInput.value = '';

            // Prepare the new history
            const newHistory = [...getHistory(), { role: 'user', content: message }];

            // Append loading message
            const loadingMessageId = appendMessage('assistant', '. . .');

            // Send AJAX request to update the history
            fetch(`/chatbots/{{ $chatbot->id }}/user-update-history`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ history: newHistory })
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
                    const updatedHistory = [...newHistory, { role: 'assistant', content: data.assistantMessage.content }];
                }
            })
            .catch(error => {
                console.error('Error:', error);
                replaceMessage(loadingMessageId, 'An error occurred while communicating with the server.');
            });
        }

        function appendMessage(role, content) {
            if (role === 'developer') return; // Ignore developer messages

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
                messageBox.classList.add('bg-gray-100');
            }
            messageBox.innerHTML = marked.parse(content); // Use marked to render markup
            messageWrapper.appendChild(messageBox);
            chatContainer.appendChild(messageWrapper);

            return messageWrapper;
        }

        function replaceMessage(messageWrapper, content) {
            messageWrapper.querySelector('div').innerHTML = marked.parse(content); // Use marked to render markup
        }

        function getHistory() {
            // This function should return the current chat history
            return @json($history) || [];
        }
    </script>
</x-app-layout>