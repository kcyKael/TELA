document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('registrationForm');

    if (!form) {
        return;
    }

    form.addEventListener('submit', function (event) {
        const fullName = document.getElementById('full_name').value.trim();
        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        const address = document.getElementById('address').value.trim();
        const contactNumber = document.getElementById('contact_number').value.trim();
        const errors = [];

        if (fullName.length < 2 || fullName.length > 100) {
            errors.push('Complete name must be 2 to 100 characters.');
        }

        if (!/^[A-Za-z .'-]+$/.test(fullName) || /^[0-9 ]+$/.test(fullName)) {
            errors.push('Complete name may contain letters, spaces, periods, hyphens, and apostrophes only.');
        }

        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) || email.length > 150) {
            errors.push('Please enter a valid email address.');
        }

        if (password.length < 8) {
            errors.push('Password must be at least 8 characters.');
        }

        if (password !== confirmPassword) {
            errors.push('Passwords do not match.');
        }

        if (address.length < 5) {
            errors.push('Complete address must be at least 5 characters.');
        }

        if (!/^[0-9+\-\s]+$/.test(contactNumber) || contactNumber.length > 20) {
            errors.push('Contact number may contain numbers, spaces, plus sign, and hyphen only.');
        }

        if (errors.length > 0) {
            event.preventDefault();
            alert(errors.join('\n'));
        }
    });
});
