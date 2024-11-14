$(function () {
    $('body').on('change', '.given-checkbox', function () {
        const isChecked = $(this).is(':checked');
        var csrfToken = $('meta[name="csrf-token"]').attr('content');
        var itemGivenUrl = $(this).data('given-url');

        $.ajax({
            url: itemGivenUrl,
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            data: JSON.stringify({
                checked: isChecked
            }),
            success: function (response) {
                console.log(response);
                $('#message')
                    .removeClass('hidden text-red-700 bg-red-100')
                    .addClass('block text-green-700 bg-green-100')
                    .text(response.message);
                if (isChecked) {
                    $('#item-given-message').removeClass('hidden');
                }
                else{
                    $('#item-given-message').addClass('hidden');
                }
            },
            error: function (response) {
                $('#message')
                    .removeClass('hidden text-green-700 bg-green-100')
                    .addClass('block text-red-700 bg-red-100')
                    .text(response.message);
                console.error("An error occurred: ", error);
            }
        });
    });
});