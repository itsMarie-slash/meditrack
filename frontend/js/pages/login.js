import { api, setCsrfToken, setCurrentUser, ApiError } from '../api.js';

const form = document.getElementById('login-form');
const errorBox = document.getElementById('login-error');
const submitBtn = document.getElementById('login-submit');

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  errorBox.style.display = 'none';
  submitBtn.disabled = true;
  submitBtn.textContent = 'Signing in…';

  try {
    const data = await api.post('/auth/login', {
      username: form.username.value.trim(),
      password: form.password.value,
    });
    setCsrfToken(data.csrf_token);
    setCurrentUser(data.user);
    location.href = 'pages/dashboard.html';
  } catch (err) {
    const message = err instanceof ApiError ? err.message : 'Unable to sign in. Please try again.';
    errorBox.textContent = message;
    errorBox.style.display = 'block';
  } finally {
    submitBtn.disabled = false;
    submitBtn.textContent = 'Sign In';
  }
});
