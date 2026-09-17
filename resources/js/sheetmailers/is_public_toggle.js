$(function () {
    $('body').on('change', '.sheetmailer-is-public-checkbox', function () {
        var $checkbox = $(this);
        var $status = $('#sheetmailer-is-public-status');
        var isChecked = $checkbox.is(':checked');
        var csrfToken = $('meta[name="csrf-token"]').attr('content');
        var toggleUrl = $checkbox.data('toggle-url');

        $checkbox.prop('disabled', true);

        $.ajax({
            url: toggleUrl,
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            data: JSON.stringify({
                checked: isChecked
            }),
            success: function (response) {
                const data = (response && response.data) || {};
                const isPublic = typeof data.is_public === 'boolean' ? data.is_public : isChecked;
                $checkbox.prop('checked', isPublic);
                $status
                    .removeClass('text-red-600')
                    .addClass('text-green-600')
                    .text(response.message);
            },
            error: function (xhr) {
                const response = xhr.responseJSON || {};
                $status
                    .removeClass('text-green-600')
                    .addClass('text-red-600')
                    .text(response.message || 'Σφάλμα ενημέρωσης κατάστασης.');
                console.error('An error occurred: ', xhr);
                // Revert the checkbox to its state before this change
                $checkbox.prop('checked', !isChecked);
            },
            complete: function () {
                $checkbox.prop('disabled', false);
            }
        });
    });
});
