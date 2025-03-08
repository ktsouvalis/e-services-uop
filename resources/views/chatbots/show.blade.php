@php
    $history = json_decode($chatbot->history, true);
@endphp
<x-app-layout>
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <h3 class="text-lg font-semibold mb-4">{{ __('Chat Dialogue') }}</h3>
            <div id="chat-container" class="space-y-4">
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Load chat history into the chat container
            const history = @json($history);
            history.forEach(message => appendMessage(message.role, message.content));
        });

        document.getElementById('send-button').addEventListener('click', function() {
            const messageInput = document.getElementById('message-input');
            const message = messageInput.value.trim();
            if (message === '') return;

            // Append user's message to the chat container
            appendMessage('user', message);

            // Clear the input field
            messageInput.value = '';

            // Prepare the new history
            const newHistory = [...getHistory(), { role: 'user', content: message }];

            // Send AJAX request to update the history
            fetch(`/chatbots/{{ $chatbot->id }}/update-history`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ history: newHistory })
            })
            .then(response => response.json())
            .then(data => {
                // Optionally handle the response data
            })
            .catch(error => console.error('Error:', error));
        });

        function appendMessage(role, content) {
            if (role === 'developer') return; // Ignore developer messages

            const chatContainer = document.getElementById('chat-container');
            const messageWrapper = document.createElement('div');
            messageWrapper.classList.add('flex', 'w-full');
            const messageBox = document.createElement('div');
            messageBox.classList.add('p-4', 'rounded-lg', 'shadow-sm', 'w-1/2', 'my-2', 'text-left', 'break-words');
            if (role === 'user') {
                messageWrapper.classList.add('justify-end');
                messageBox.classList.add('bg-yellow-100');
            } else if (role === 'assistant') {
                messageWrapper.classList.add('justify-start');
                messageBox.classList.add('bg-gray-100');
            }
            messageBox.textContent = content;
            messageWrapper.appendChild(messageBox);
            chatContainer.appendChild(messageWrapper);
        }

        function getHistory() {
            // This function should return the current chat history
            return @json($history);
        }
    </script>
</x-app-layout>
