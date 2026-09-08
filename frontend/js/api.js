// Central fetch wrapper: attaches credentials + CSRF header, unwraps the
// standard { success, message, data } envelope, and normalizes errors.
const API_BASE = window.MEDITRACK_API_BASE || 'http://localhost:8000/api';

export class ApiError extends Error {
  constructor(message, status, errors) {
    super(message);
    this.status = status;
    this.errors = errors || null;
  }
}

function csrfToken() {
  return sessionStorage.getItem('csrf_token') || '';
}

export function setCsrfToken(token) {
  sessionStorage.setItem('csrf_token', token);
}

export function setCurrentUser(user) {
  sessionStorage.setItem('current_user', JSON.stringify(user));
}

export function getCurrentUser() {
  const raw = sessionStorage.getItem('current_user');
  return raw ? JSON.parse(raw) : null;
}

export function clearSession() {
  sessionStorage.removeItem('csrf_token');
  sessionStorage.removeItem('current_user');
}

async function request(method, path, { query, body } = {}) {
  let url = `${API_BASE}${path}`;
  if (query) {
    const params = new URLSearchParams();
    Object.entries(query).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== '') params.set(key, value);
    });
    const qs = params.toString();
    if (qs) url += `?${qs}`;
  }

  const headers = {};
  if (body !== undefined) headers['Content-Type'] = 'application/json';
  if (!['GET', 'HEAD'].includes(method)) headers['X-CSRF-Token'] = csrfToken();

  let response;
  try {
    response = await fetch(url, {
      method,
      credentials: 'include',
      headers,
      body: body !== undefined ? JSON.stringify(body) : undefined,
    });
  } catch (networkError) {
    throw new ApiError('Network error — please check your connection and try again.', 0);
  }

  let payload = null;
  const contentType = response.headers.get('content-type') || '';
  if (contentType.includes('application/json')) {
    payload = await response.json().catch(() => null);
  }

  if (!response.ok) {
    if (response.status === 401) {
      clearSession();
      if (!location.pathname.endsWith('/index.html') && location.pathname !== '/') {
        location.href = '/index.html';
      }
    }
    const message = payload?.message || 'Something went wrong. Please try again.';
    const errors = payload?.data?.errors || null;
    throw new ApiError(message, response.status, errors);
  }

  return payload?.data ?? {};
}

export const api = {
  get: (path, query) => request('GET', path, { query }),
  post: (path, body) => request('POST', path, { body: body ?? {} }),
  put: (path, body) => request('PUT', path, { body: body ?? {} }),
  del: (path) => request('DELETE', path, {}),
};
