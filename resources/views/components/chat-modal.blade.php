<div id="chat-modal" class="chat-modal pb-4" style="display: none;">
    <div class="chat-header">
        <span class="close-chat-modal">&times;</span> <!-- Close button inside the modal -->
        Chat
    </div>
    <div class="chat-messages" id="chat-messages">
        <!-- Messages will be appended here dynamically -->
    </div>
    <hr>
    <form id="chat-form" action="{{ route('chat.send-message') }}" method="POST">
        @csrf
        <input type="text" id="chat-input" placeholder="Type a message..." required>
        <button type="submit" id="send-message" style="display: none">Send</button>
        {{-- <button type="submit" id="send-message">Send</button> --}}
    </form>
</div>
