<div id="chat-modal" class="chat-modal pb-4" style="display: none;">
    <div class="chat-header flex items-center justify-between px-3 py-2">
        <div class="flex items-center space-x-2">
            <button id="clear-chat-button" class="bg-red-500 hover:bg-red-600 text-white rounded px-1 py-1 text-sm" title="Delete history">
                <!-- simple trash icon -->
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-trash" viewBox="0 0 16 16">
                <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/>
                <path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/>
                </svg></button>
            <span class="close-chat-modal cursor-pointer text-2xl">&times;</span> <!-- Close button inside the modal -->
            <span class="font-semibold">Chat</span>
        </div>
    </div>
    <div class="chat-messages" id="chat-messages">
        <!-- Messages will be appended here dynamically -->
    </div>
    <hr>
    <form id="chat-form" action="{{ route('chat.send-message') }}" method="POST">
        @csrf
        <input type="text" id="chat-input" placeholder="Type a message..." required style="width: 100%; box-sizing: border-box;">
        <button type="submit" id="send-message" style="display: none">Send</button>
        {{-- <button type="submit" id="send-message">Send</button> --}}
    </form>
</div>
