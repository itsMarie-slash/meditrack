# MediTrack

Web-based senior citizen healthcare and medicine monitoring system for
Barangay New Bulatukan Health Center — a capstone project for tracking
hypertension/diabetes maintenance-medicine distribution, replacing manual
paper records with a system that manages beneficiary information, medicine
inventory, distribution scheduling with automated SMS reminders, GIS
mapping of beneficiaries, and demand forecasting for IPHO supply planning.

## Documentation

Design and requirements docs live in `docs/`, written in the order they
were developed:

1. [`01-requirements-analysis.md`](docs/01-requirements-analysis.md) — functional/non-functional requirements, roles, assumptions
2. [`02-architecture.md`](docs/02-architecture.md) — tech stack, folder structure, full REST API spec
3. [`03-ui-ux.md`](docs/03-ui-ux.md) — page-by-page UI spec
4. [`04-database.md`](docs/04-database.md) — ERD and schema notes (DDL in `database/schema.sql`)
5. [`07-testing.md`](docs/07-testing.md) — what's tested and how to run it
6. [`08-deployment.md`](docs/08-deployment.md) — setup, configuration, and a go-live checklist

## Project layout

```
backend/    PHP 8 REST API (custom router, PDO repositories, PHPUnit tests)
frontend/   Static HTML/CSS/vanilla-JS UI, no build step
database/   MySQL schema + seed data
tests/e2e/  Playwright end-to-end tests against a running instance
docs/       Design docs (see above)
```

## Quick start (local development)

```bash
# 1. Database
mysql -u root -p -e "CREATE DATABASE meditrack CHARACTER SET utf8mb4;"
mysql -u root -p meditrack < database/schema.sql
mysql -u root -p meditrack < database/seed.sql

# 2. Backend
cd backend
composer install
cp .env.example .env   # edit DB credentials; set SESSION_COOKIE_SECURE=false for local HTTP
php -S 127.0.0.1:8099 -t public

# 3. Frontend (separate terminal)
cd frontend
php -S 127.0.0.1:8098
# edit js/config.js if the backend isn't on http://localhost:8000/api
```

Then open `http://127.0.0.1:8098/index.html` and sign in with one of the
seeded demo accounts (`bhw.admin`, `midwife.demo`, or `ipho.demo`, password
`ChangeMe123!` — change these before any real deployment).

Full setup, HTTPS, and production notes: [`docs/08-deployment.md`](docs/08-deployment.md).
