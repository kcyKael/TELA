document.addEventListener('DOMContentLoaded', function () {
    const alerts = document.querySelectorAll('.alert[data-auto-hide="true"]');

    alerts.forEach(function (alertBox) {
        setTimeout(function () {
            alertBox.classList.add('d-none');
        }, 4000);
    });
});
