document.addEventListener('DOMContentLoaded', function () {
    document.body.addEventListener('change', function (event) {
        if (event.target.classList.contains('given-checkbox')) {
            // const itemId = event.target.getAttribute('data-item-id');
            const isChecked = event.target.checked;
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const itemGivenUrl = event.target.getAttribute('data-given-url');

            fetch(itemGivenUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    checked: isChecked
                })
            })
            .then(response => response.json())
            .then(data => {
                console.log(data);
            })
            .catch(error => {
                console.error("An error occurred: ", error);
            });
        }
    });
});