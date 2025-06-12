document.addEventListener('DOMContentLoaded', function () {
    document.body.addEventListener('click', function (event) {
        // Find the closest element with the class 'mark-notification'
        let target = event.target;
        if (!target.classList.contains('mark-notification')) {
            target = target.closest('.mark-notification');
        }
        if (target && target.classList.contains('mark-notification')) {
            const notificationId = target.getAttribute('data-notification-id');

            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            if (!csrfMeta) return;
            const csrfToken = csrfMeta.getAttribute('content');

            if (typeof markNotificationAsReadUrl === 'undefined') return;
            const url = markNotificationAsReadUrl.replace("mpla", notificationId);

            fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            })
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                // Update the UI
                const markCell = document.querySelector('.mark-' + notificationId);
                if (markCell) {
                    // Remove the unread icon
                    const unreadIcon = markCell.querySelector('#icon' + notificationId);
                    if (unreadIcon) unreadIcon.remove();
                    // Remove all children (including any SVGs)
                    markCell.innerHTML = '';
                    // Add the read icon (Heroicon Envelope Open)
                    markCell.insertAdjacentHTML('beforeend',
                        `<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-400 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8m-18 8V8a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                        </svg>`
                    );
                }
                // Remove the mark as read button
                const markBtn = document.getElementById('mark' + notificationId);
                if (markBtn) markBtn.remove();
                // Remove the unread background color and any red outline
                const row = document.getElementById('notification-' + notificationId);
                if (row) {
                    row.classList.remove(
                        'bg-green-100',
                        'table-secondary',
                        'border',
                        'border-red-600',
                        'border-red-500',
                        'text-red-600',
                        'ring-2',
                        'ring-red-600',
                        'ring-red-500'
                    );
                    // Also remove any inline border color
                    row.style.border = '';
                }

                // Change the notification icon in the navigation if no unread notifications remain
                const unreadRows = document.querySelectorAll('tr.bg-green-100');
                if (unreadRows.length === 0) {
                    // Replace unread bell with read bell in navigation
                    const navBell = document.querySelector('a[data-toggle="tooltip"][title="Ειδοποιήσεις"] svg');
                    if (navBell) {
                        navBell.outerHTML = `
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                        `;
                        // Optionally, also update the color class
                        const navLink = navBell.closest('a');
                        if (navLink) {
                            navLink.classList.remove('text-red-600', 'text-danger');
                            navLink.classList.add('text-gray-500');
                        }
                    }
                }
            })
            .catch(error => {
            });
        }
    });
});