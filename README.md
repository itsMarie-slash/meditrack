# MediTrack

Web-based Senior Citizen Healthcare and Medicine Monitoring System for the
**Barangay New Bulatukan Health Center** — a capstone project replacing
manual, paper-based tracking of senior citizen beneficiaries and their
maintenance-medicine distribution.

Built with plain PHP (no framework), mysqli prepared statements, MySQL,
Bootstrap 5, Leaflet.js (GIS), Chart.js (forecasting), and the Semaphore SMS
API.

## Features

- **Beneficiary registration & directory** — searchable, filterable,
  paginated, with a map-click location picker.
- **Medicine inventory monitoring** — stock levels, reorder thresholds,
  expiry tracking, and a full audit trail of every stock transaction.
- **Distribution monitoring** — schedule and dispense medicine per
  beneficiary; marking a record "Dispensed" atomically decrements stock.
- **Demand forecasting** — Simple Exponential Smoothing over past
  distribution cycles, recomputed automatically after every dispense.
- **GIS mapping** — Leaflet map of beneficiaries color-coded by their latest
  distribution status.
- **SMS notifications** — automated upcoming-distribution reminders (via a
  scheduled script) and manual program-update broadcasts, all logged.
- **Role-based access** — BHW (full access/admin), Midwife (full clinical
  access), IPHO (read-only + forecasting/reports).

## Requirements

- PHP 8.x with the `mysqli` and `curl` extensions
- MySQL / MariaDB
- A Semaphore SMS account + API key (optional — the app runs fine without
  one, SMS sends will just fail and be logged as such)

## Setup (XAMPP)

1. Copy this project into `htdocs/meditrack`.
2. Create the database and import the schema:
   ```sql
   CREATE DATABASE meditrack CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
   ```
   Then run `sql/schema.sql` (table structure) followed by `sql/seed.sql`
   (test accounts + sample data) through phpMyAdmin or the `mysql` CLI.
3. Copy `config/config.example.php` to `config/config.php` and fill in your
   database credentials and Semaphore API key/sender name. `config/config.php`
   is gitignored, so real credentials never get committed.
4. Start Apache + MySQL in XAMPP and visit `http://localhost/meditrack/`.

### Test accounts (from `sql/seed.sql`)

| Role     | Email                     | Password    |
|----------|---------------------------|-------------|
| BHW      | bhw@meditrack.test        | bhw123      |
| Midwife  | midwife@meditrack.test    | midwife123  |
| IPHO     | ipho@meditrack.test       | ipho123     |

### SMS reminders (scheduled task)

`cron/send_reminders.php` is a standalone CLI script that sends reminder SMS
for distributions scheduled within the next `SMS_REMINDER_DAYS_BEFORE` days
(default 2). On Windows/XAMPP, schedule it with Task Scheduler:

```
php.exe C:\xampp\htdocs\meditrack\cron\send_reminders.php
```

On a Linux deployment, use a standard cron entry instead:

```
0 8 * * * /usr/bin/php /path/to/meditrack/cron/send_reminders.php
```

## Project structure

See inline comments in each folder; roughly:

- `config/` — database connection + site/SMS settings (gitignored secrets)
- `includes/` — session/auth, CSRF, shared helper functions
- `models/` — one file per table, all mysqli prepared-statement queries
- `services/` — ForecastService (demand forecasting) and SemaphoreSmsService
- `views/partials/` — shared header/footer/sidebar
- `bhw/` — operational pages (shared by the BHW and Midwife roles)
- `ipho/` — read-only reporting + forecasting pages for IPHO staff
- `cron/` — scheduled SMS reminder script
- `sql/` — schema + seed data

**Note on roles/folders:** the BHW role is treated as this system's
full-access/admin role. The Midwife role shares the same `/bhw/` operational
pages (registration, inventory, distribution) since both need full CRUD on
clinical data — each page still calls `requireRole()` to enforce this, it is
not just a hidden menu link. IPHO gets its own read-only `/ipho/` pages plus
full access to forecasting and CSV export.
