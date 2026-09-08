# MediTrack — Phase 8: Deployment

## 1. Requirements

- PHP 8.1+ with `pdo_mysql`, `curl`, `json`, `mbstring` extensions.
- MySQL 8.x (or MariaDB 10.6+).
- Composer.
- A web server (Apache or Nginx) able to serve `backend/public/` as PHP and
  `frontend/` as static files — either on the same origin (simplest) or two
  origins with CORS (see `FRONTEND_ORIGIN` below).
- A Semaphore SMS account + API key for FR-6 (optional in development —
  `SMS_DRY_RUN=true` logs instead of sending).

## 2. Database setup

```bash
mysql -u root -p -e "CREATE DATABASE meditrack CHARACTER SET utf8mb4;"
mysql -u root -p -e "CREATE USER 'meditrack_app'@'localhost' IDENTIFIED BY '<strong-password>';"
mysql -u root -p -e "GRANT ALL PRIVILEGES ON meditrack.* TO 'meditrack_app'@'localhost';"
mysql -u root -p meditrack < database/schema.sql
mysql -u root -p meditrack < database/seed.sql   # demo accounts + placeholder puroks
```

Before going live: replace the placeholder puroks in `seed.sql` with
Barangay New Bulatukan's real purok names and coordinates (see
docs/01-requirements-analysis.md §13.3), and change every seeded account's
password on first login — they all share one demo password
(`ChangeMe123!`) out of the box.

## 3. Backend configuration

```bash
cd backend
composer install --no-dev --optimize-autoloader   # add --no-dev only for production
cp .env.example .env
```

Edit `.env`:
- `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` — match step 2.
- `SESSION_COOKIE_SECURE=true` — **required** once served over HTTPS; only
  set to `false` for local HTTP-only development.
- `FRONTEND_ORIGIN` — the exact origin the frontend is served from (needed
  for the CORS + credentialed-cookie flow); leave both on the same origin
  in production to avoid the extra complexity.
- `SEMAPHORE_API_KEY`, `SEMAPHORE_SENDER_NAME`, `SMS_DRY_RUN=false` — once
  a Semaphore account exists.

Point your web server's document root at `backend/public/` with all
requests rewritten to `index.php` (the front controller). Example Nginx
snippet:

```nginx
location /api/ {
    try_files $uri /index.php$is_args$args;
}
location ~ \.php$ {
    fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $document_root/index.php;
    include fastcgi_params;
}
```

## 4. Frontend configuration

Edit `frontend/js/config.js` — set `window.MEDITRACK_API_BASE` to the
backend's `/api` URL (e.g. `https://meditrack.example.com/api`). Serve the
`frontend/` directory as static files (any web server, or the same Nginx
vhost as the backend on a different path).

No build step: it's plain HTML/CSS/JS. "Production build" is just deploying
the files as-is.

## 5. Server/security configuration

- Serve everything over HTTPS — required for `SESSION_COOKIE_SECURE` and to
  protect login credentials and health data in transit.
- Set `APP_ENV=production` and keep `APP_DEBUG=false` in `.env`.
- Ensure `.env` is not web-readable (it lives outside `public/`, which is
  already the case in this layout — only `backend/public/` should be the
  web root).
- PHP `session.gc_maxlifetime` should be ≥ `SESSION_LIFETIME_SECONDS`.
- Run the app under a low-privilege OS user (no root).

## 6. Backups and recovery (NFR-6)

- Nightly `mysqldump` (or your host's managed-MySQL backup feature) of the
  `meditrack` database, retained per your data-retention policy.
- Test the restore path periodically:
  `mysql -u root -p meditrack_restore_test < backup.sql`.
- Target: restore within 24 hours of a server failure, per NFR-6. Document
  who holds backup access and how they're rotated — this is an
  organizational decision outside this codebase's scope.

## 7. Monitoring

- Application errors are written to the PHP error log (`error_log()` calls
  in `index.php`, `SmsService`, `Database`) — ship that log to wherever your
  host aggregates logs.
- The `audit_logs` table already captures login attempts and every
  create/update/delete/stock-update — a lightweight dashboard or scheduled
  report over that table covers most "is anything going wrong" questions
  without new instrumentation.
- Watch `sms_notifications.status = 'failed'` — the dashboard already
  surfaces a count of these to BHW users (see FR-6 / NFR-7's 1-minute SLA).

## 8. Deployment checklist

- [ ] Real purok data seeded (not placeholders)
- [ ] All demo account passwords changed
- [ ] `.env` configured with production DB + Semaphore credentials
- [ ] `SESSION_COOKIE_SECURE=true`, HTTPS enforced
- [ ] `composer install --no-dev` run
- [ ] `frontend/js/config.js` points at the production API URL
- [ ] Backup job scheduled and one restore tested
- [ ] `backend/tests` (PHPUnit) and `tests/e2e` (Playwright) both green
      against a staging copy of this configuration
