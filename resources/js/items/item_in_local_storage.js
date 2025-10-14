$(function () {
    function syncMutualExclusion(givenChecked, localChecked) {
        const $given = $('.given-checkbox');
        const $local = $('.in-local-storage-checkbox');

        if (givenChecked) {
            $given.prop('checked', true);
            $local.prop('checked', false).prop('disabled', true);
        } else {
            $given.prop('checked', false);
            $local.prop('disabled', false);
        }
        if (localChecked) {
            $local.prop('checked', true);
            $given.prop('checked', false).prop('disabled', true);
        } else if (!givenChecked) {
            $given.prop('disabled', false);
        }
    }

    // Initial state
    (function init() {
        const givenChecked = $('.given-checkbox').is(':checked');
        const localChecked = $('.in-local-storage-checkbox').is(':checked');
        syncMutualExclusion(givenChecked, localChecked);
    })();

    $('body').on('change', '.in-local-storage-checkbox', function () {
        var isChecked = $(this).is(':checked');
        var csrfToken = $('meta[name="csrf-token"]').attr('content');
        var toggleUrl = $(this).data('toggle-url');

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
                const given = typeof data.given_away === 'boolean' ? data.given_away : !isChecked;
                const local = typeof data.in_local_storage === 'boolean' ? data.in_local_storage : isChecked;
                syncMutualExclusion(given, local);
                $('#message')
                    .removeClass('hidden text-red-700 bg-red-100')
                    .addClass('block text-green-700 bg-green-100')
                    .text(response.message);
            },
            error: function (response) {
                $('#message')
                    .removeClass('hidden text-green-700 bg-green-100')
                    .addClass('block text-red-700 bg-red-100')
                    .text(response.message || 'Σφάλμα ενημέρωσης κατάστασης.');
                console.error('An error occurred: ', response);
                // Revert UI
                const givenChecked = $('.given-checkbox').is(':checked');
                const localChecked = $('.in-local-storage-checkbox').is(':checked');
                syncMutualExclusion(givenChecked, localChecked);
            }
        });
    });
});
