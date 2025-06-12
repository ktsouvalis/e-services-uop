document.addEventListener('DOMContentLoaded', function () {
    document.body.addEventListener('click', function (event) {
        // Find the closest element with the class 'delete-notification'
        let target = event.target;
        if (!target.classList.contains('delete-notification')) {
            target = target.closest('.delete-notification');
        }
        if (target && target.classList.contains('delete-notification')) {
            const notificationId = target.getAttribute('data-notification-id');

            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            if (!csrfMeta) return;
            const csrfToken = csrfMeta.getAttribute('content');

            if (typeof deleteNotificationUrl === 'undefined') return;
            const url = deleteNotificationUrl.replace("mpla", notificationId);

            fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ _method: 'DELETE' })
            })
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                // Add strikethrough effect to all cells in the row
                const row = document.getElementById('notification-' + notificationId);
                if (row) {
                    // Remove green/yellow background if present
                    row.classList.remove('bg-green-100', 'bg-yellow-100');
                    Array.from(row.children).forEach((cell, idx) => {
                        cell.classList.add('line-through', 'text-gray-400');
                        // Remove mark as read and delete buttons if present
                        const markBtn = cell.querySelector('.mark-notification');
                        if (markBtn) markBtn.remove();
                        const deleteBtn = cell.querySelector('.delete-notification');
                        if (deleteBtn) deleteBtn.remove();
                        // Remove clickable href from the second column (index 1)
                        if (idx === 1) {
                            const link = cell.querySelector('a');
                            if (link) {
                                const text = link.textContent;
                                link.replaceWith(document.createTextNode(text));
                            }
                        }
                        // Add deleted/trash icon to the first cell (status)
                        if (idx === 0) {
                            // Remove any existing icons
                            cell.innerHTML = '';
                            cell.insertAdjacentHTML('afterbegin',
                                `<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-400 inline mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M10 3h4a2 2 0 012 2v2H8V5a2 2 0 012-2z"/>
                                </svg>`
                            );
                        }
                    });
                }
            })
            .catch(error => {
                console.error("An error occurred in delete_notification.js: ", error);
            });
        }
    });
});