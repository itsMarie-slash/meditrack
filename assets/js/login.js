/**
 * assets/js/login.js
 * -----------------------------------------------------------------
 * Client-side validation for the login form.
 * This is a first line of defense only (better user experience,
 * fewer wasted round-trips). The real security check happens in
 * modules/auth/process_login.php on the server - never trust
 * client-side validation alone.
 * -----------------------------------------------------------------
 */

document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('loginForm');
    if (!form) {
        return;
    }

    var usernameInput = document.getElementById('username');
    var passwordInput = document.getElementById('password');
    var usernameError = document.getElementById('usernameError');
    var passwordError = document.getElementById('passwordError');
    var submitBtn = document.getElementById('loginBtn');

    function showError(inputEl, errorEl, message) {
        errorEl.textContent = message;
        errorEl.style.display = 'block';
        inputEl.style.borderColor = '#c0392b';
    }

    function clearError(inputEl, errorEl) {
        errorEl.textContent = '';
        errorEl.style.display = 'none';
        inputEl.style.borderColor = '#b7d9e8';
    }

    form.addEventListener('submit', function (e) {
        var isValid = true;

        var username = usernameInput.value.trim();
        var password = passwordInput.value;

        if (username === '') {
            showError(usernameInput, usernameError, 'Username is required.');
            isValid = false;
        } else if (username.length < 3) {
            showError(usernameInput, usernameError, 'Username must be at least 3 characters.');
            isValid = false;
        } else {
            clearError(usernameInput, usernameError);
        }

        if (password === '') {
            showError(passwordInput, passwordError, 'Password is required.');
            isValid = false;
        } else if (password.length < 6) {
            showError(passwordInput, passwordError, 'Password must be at least 6 characters.');
            isValid = false;
        } else {
            clearError(passwordInput, passwordError);
        }

        if (!isValid) {
            e.preventDefault();
        } else {
            // Prevent double-submission while the request is processing
            submitBtn.disabled = true;
            submitBtn.textContent = 'Signing in...';
        }
    });

    // Clear the error message as soon as the user starts fixing the field
    usernameInput.addEventListener('input', function () {
        clearError(usernameInput, usernameError);
    });
    passwordInput.addEventListener('input', function () {
        clearError(passwordInput, passwordError);
    });
});
