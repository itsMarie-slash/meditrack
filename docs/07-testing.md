# MediTrack — Phase 7: Testing

## What's covered

**Backend unit tests** (`backend/tests/Unit`, PHPUnit — 20 tests):
- `ValidatorTest` — required-field, PH mobile format, future-date rejection,
  `in:` enum rule, and the "blank optional field must not fail validation"
  case that caused a real bug during manual testing.
- `ForecastServiceTest` — the exponential-smoothing forecasting algorithm:
  empty/short history, and that recent months are weighted more heavily
  than older ones.
- `MedicineServiceTest` — the core FR-3/FR-4/FR-6 business rule end to end
  with mocked repositories: stock crossing the low-stock threshold creates
  a schedule and SMS-notifies every assigned beneficiary; stock that stays
  below or was already above the threshold does not; a failed SMS send is
  recorded as `failed`, not silently dropped.

Run with:
```bash
cd backend
composer install
./vendor/bin/phpunit
```

**End-to-end tests** (`tests/e2e`, Playwright — 9 tests) against the real
backend + a real database:
- Authentication: invalid login rejected, valid login reaches the dashboard.
- RBAC: each role's sidebar shows only its permitted modules; Midwife has no
  CRUD buttons on Senior Citizens; **a Midwife session calling `GET
  /api/users` directly gets a real 403** — proving enforcement is
  server-side, not just a hidden link (the requirement in Phase 1 §4: "Users
  must never be able to access restricted functions simply by manually
  entering a URL").
- Senior citizen CRUD through the actual form, plus a validation-error case
  that must surface as an inline field error, not a raw 500.
- The stock-update trigger exercised through the real "Update Stock" modal.

See `tests/e2e/README.md` for how to run these against a local database.

Both suites were run against a live MariaDB instance during development;
that process caught and fixed four real bugs before they reached this
branch (documented in the commit history): a case-sensitive CSRF header
lookup, three PDO statements reusing a named placeholder twice, and two
empty-string-to-NULL/default normalization gaps on optional form fields.

## What's not automated here

- **Authorization matrix beyond the sample above.** The RBAC test suite
  checks one representative "hidden link + blocked API" pair (Midwife vs.
  `/api/users`); it does not enumerate every role × endpoint combination
  from docs/02-architecture.md §5. Worth expanding before a real deployment.
- **Load/performance testing** against NFR-1's targets (500 beneficiaries,
  GIS map in 3-5s, forecasting in ≤10s) — not exercised here; the seed data
  is a handful of rows.
- **SMS gateway integration** is tested only in dry-run mode (`SMS_DRY_RUN`)
  — a real Semaphore account is needed to test the live HTTP call path in
  `SmsService::send()`.
- **Cross-browser/responsive testing** — Playwright runs were Chromium-only
  at the desktop breakpoint.
