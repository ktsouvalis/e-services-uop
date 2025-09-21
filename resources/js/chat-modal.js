// Clear saved chat messages and modal state from localStorage
function clearChatLocalStorage() {
    // Confirm with the user before clearing
    if (!confirm('Delete all saved chat messages from previous sessions? This cannot be undone.')) {
        return;
    }

    localStorage.removeItem('chatMessages');
    localStorage.removeItem('chatModalOpen');

    // Clear UI messages if the container exists
    const chatMessagesContainer = document.querySelector('.chat-messages');
    if (chatMessagesContainer) {
        chatMessagesContainer.innerHTML = '';
    }

    // Optionally provide visual feedback
    alert('Saved chat messages deleted.');
}

// Initialize Echo and listen for the MessageSent event
function initializeEcho() {
    window.Echo.private('dgu-chatroom')
        .listen('MessageSent', (e) => {
            console.log(e);
            appendMessageToChat(e.user, e.message);
            saveMessageToLocalStorage(e.user, e.message); // Save to localStorage when a message is received
        });
}

// Append the received message to the chat window
function appendMessageToChat(user, message) {
    let messageDiv = document.createElement('div');
    messageDiv.classList.add('message');
    messageDiv.innerHTML = `<strong>${user}:</strong> ${message}`;
    document.querySelector('.chat-messages').appendChild(messageDiv);
}

// Save the message to localStorage to persist it across page reloads
function saveMessageToLocalStorage(user, message) {
    let messages = JSON.parse(localStorage.getItem('chatMessages')) || [];
    messages.push({ user, message });
    localStorage.setItem('chatMessages', JSON.stringify(messages));
}

// Display messages from localStorage when the modal is opened
function displayMessages() {
    const messages = JSON.parse(localStorage.getItem('chatMessages')) || [];
    const chatMessagesContainer = document.querySelector('.chat-messages');
    chatMessagesContainer.innerHTML = ''; // Clear existing messages

    messages.forEach(msg => {
        appendMessageToChat(msg.user, msg.message);
    });
}

// Show or hide the chat modal based on its current state
function toggleChatModal() {
    const chatModal = document.getElementById('chat-modal');
    const isModalVisible = chatModal.style.display === 'block';

    // Toggle modal display and save state in localStorage
    chatModal.style.display = isModalVisible ? 'none' : 'block';
    localStorage.setItem('chatModalOpen', !isModalVisible);
}

// Close the modal when the close button is clicked
function closeChatModal() {
    const chatModal = document.getElementById('chat-modal');
    chatModal.style.display = 'none';
    localStorage.setItem('chatModalOpen', false); // Update the localStorage state
}

// Check localStorage for chat modal state and open if needed
function loadChatModalState() {
    const chatModal = document.getElementById('chat-modal');
    const isChatOpen = localStorage.getItem('chatModalOpen') === 'true';

    if (isChatOpen) {
        chatModal.style.display = 'block';
        displayMessages(); // Load and display saved messages when modal is opened
    }
}

// Save the modal state to localStorage when the user logs out
function logoutChatModalState() {
    localStorage.removeItem('chatModalOpen');
    localStorage.removeItem('chatMessages'); // Optionally, clear the saved messages on logout
}

// Handle form submission to send a message
function handleFormSubmit(event) {
    event.preventDefault();

    const textarea = document.getElementById('chat-input');
    let content = textarea.value.trim();

    if (content === '') {
        return;
    }

    sendMessage(content);
    textarea.value = ''; // Clear the textarea after sending the message
}

// Send the message via fetch
function sendMessage(content) {
    const form = document.getElementById('chat-form');

    fetch(form.action, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ content })
    })
    // .then(response => response.json()) // Optional: You can handle the response here if needed
}

// Function to toggle chat modal visibility when the "chat-toggle-button" link is clicked
function toggleChatOnClick() {
    const toggleBtn = document.getElementById('chat-toggle-button');
    if (!toggleBtn) return;

    toggleBtn.addEventListener('click', function(event) {
        event.preventDefault();
        toggleChatModal();
    });
}

// Event listener for logout (removes chat modal state from localStorage)
function logoutChatStateOnClick() {
    const logoutLink = document.getElementById('logout-link');
    if (!logoutLink) return;

    logoutLink.addEventListener('click', logoutChatModalState);
}

// Initialize event listener for the close button in the modal
function initializeCloseButton() {
    const closeBtn = document.querySelector('.close-chat-modal');
    if (!closeBtn) return;

    closeBtn.addEventListener('click', closeChatModal);
}

// Initialize all functions and set up event listeners
function init() {
    // Toggle chat modal when the open-chat button is clicked
    toggleChatOnClick();

    // Load chat modal state on page load
    window.addEventListener('load', loadChatModalState);

    // Handle logout event and remove modal state
    logoutChatStateOnClick();

    // Handle form submission for chat messages
    const form = document.getElementById('chat-form');
    form.addEventListener('submit', handleFormSubmit);

    // Initialize Echo for real-time message updates
    initializeEcho();

    // Initialize close button functionality
    initializeCloseButton();

    // Wire up the clear/delete button we added to the modal header
    const clearBtn = document.getElementById('clear-chat-button');
    if (clearBtn) {
        clearBtn.addEventListener('click', clearChatLocalStorage);
    }
}

// Call the init function when the script is loaded
init();
