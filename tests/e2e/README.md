# MediTrack E2E Tests

Browser-driven smoke tests (Playwright) covering the workflows a unit test
can't: login, role-based UI differences, and server-enforced RBAC on top of
the real API.

## Prerequisites

1. A MySQL database loaded from `database/schema.sql` + `database/seed.sql`.
2. The backend running (`php -S 127.0.0.1:8099 -t backend/public`, with
   `backend/.env` pointed at that database and `SESSION_COOKIE_SECURE=false`
   for local HTTP testing).
3. The frontend served as static files (`php -S 127.0.0.1:8098` from the
   `frontend/` directory).

## Running

```bash
cd tests/e2e
npm install
BACKEND_URL=http://127.0.0.1:8099/api npx playwright test
```

Set `FRONTEND_URL` if the frontend isn't on the default `http://127.0.0.1:8098`.

Tests use timestamp-suffixed names so repeated runs against the same
database don't collide on unique constraints, but they do write real rows
(a test beneficiary, a stock batch) — point this at a scratch database, not
production data.
