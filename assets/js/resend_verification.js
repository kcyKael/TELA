document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('resendVerificationForm');

    if (!form) {
        return;
    }

    form.addEventListener('submit', function (event) {
        const email = document.getElementById('email').value.trim();
        const errors = [];

        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) || email.length > 150) {
            errors.push('Please enter a valid email address.');
        }

        if (errors.length > 0) {
            event.preventDefault();
            alert(errors.join('\n'));
        }
    });
});
