
$(function () {
    $('body').on('click', '.item-delbox', function () {
        // Confirmation dialog
        const isConfirmed = confirm('Επιβεβαίωση διαγραφής αντικειμένου;');
        if (!isConfirmed) {
            return;
        }
        const itemId = $(this).data('item-id');
        // Get the CSRF token from the meta tag
        const csrfToken = $('meta[name="csrf-token"]').attr('content');
        const itemDeleteUrl = $(this).data('delete-url');

        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': csrfToken
            }
        });

        $.ajax({
            url: itemDeleteUrl,
            type: 'POST',
            data: {
                _method: 'DELETE', // Laravel uses PATCH for updates
            },
            success: function (response) {
                // Handle the response here, update the page as needed
                $('#item-' + itemId).remove();
                $('#message')
                    .removeClass('hidden text-red-700 bg-red-100')
                    .addClass('block text-green-700 bg-green-100')
                    .text(response.message);
            },
            error: function (response) {
                // Handle errors
                $('#message')
                    .removeClass('hidden text-green-700 bg-green-100')
                    .addClass('block text-red-700 bg-red-100')
                    .text(response.message);
                console.log("An error occurred: " + error);
            }
        });
    });
});