# MediTrack — Phase 1: Requirements Analysis

Per the specified development process, this document is the Phase 1 deliverable
(Requirements Analysis) and precedes any code. Phases 2–8 (architecture, UI/UX,
database, backend, frontend, testing, deployment) begin only after this phase
is reviewed and approved.

## 1. System Overview

**Name:** MediTrack
**Type:** Web-based inventory, information-management, monitoring, and
tracking system for senior-citizen maintenance-medicine distribution.
**Owner:** Barangay New Bulatukan Health Center.

**Problem statement.** The health center currently manages hypertension and
diabetes maintenance-medicine distribution manually:

- Beneficiary information is recorded on printed forms, risking incomplete,
  unclear, or inaccurate records.
- Medicine stock is tracked manually, causing discrepancies between physical
  inventory and recorded quantities.
- Distribution announcements rely on word of mouth, causing delayed or missed
  information.
- Medicine supplied by IPHO to the health center is often inaccurate in
  quantity or delayed, causing shortages or excess stock.

**Goals.** Replace the manual process with a system that:

1. Digitizes and centralizes beneficiary records.
2. Tracks medicine stock against physical inventory.
3. Sends automated SMS notifications for distribution schedules and changes.
4. Forecasts future medicine demand from historical distribution data, so
   IPHO can supply accurate quantities.

## 2. Functional Requirements

| # | Requirement | Description |
|---|---|---|
| FR-1 | User login and authentication | BHWs, Midwife, and IPHO log in with a unique username/password; access is scoped by role. |
| FR-2 | Senior citizen information management | BHWs add/edit/view/manage beneficiary records: full name, birthdate, gender, purok address, mobile number, guardian contact, medical condition (hypertension/diabetes), assigned maintenance medicine, distribution status. |
| FR-3 | Medicine inventory management | Encode/update/manage medicine records: name, category, description, stock quantity, low-stock threshold, unit of measure, batch number, expiration date, date received. |
| FR-4 | Automatic distribution-schedule creation | A distribution schedule is auto-generated when a stock update raises available stock to a distributable level. Includes date, time slot, venue, and status (default "Upcoming"). |
| FR-5 | Distribution monitoring and tracking | Log each distribution transaction: recipient, medicine, quantity issued, date released, receiver name, BHW in charge, status (pending/completed/cancelled). |
| FR-6 | Automated SMS notification | SMS sent to beneficiaries automatically when a stock update triggers a new distribution schedule; message includes date, time, venue, and a pickup reminder. |
| FR-7 | Search and retrieval | BHWs and Midwife can search/filter senior-citizen profiles, medicine records, distribution logs, and schedules by keyword/filter. |
| FR-8 | GIS mapping visualization | Interactive map (pin markers by purok address) of registered beneficiaries within Barangay New Bulatukan; supports zoom, pan, and click-for-details. |
| FR-9 | Beneficiary location filtering on map | Filter the map by purok, medical condition, or distribution status. |
| FR-10 | Beneficiary details on map | Clicking a pin shows name, medical condition, assigned medicine, distribution status. |
| FR-11 | Demand forecasting generation | Auto-generate a forecast of required quantity per medicine for the next distribution period, from historical distribution records. |
| FR-12 | Demand forecast visualization | Present forecasts as charts (line/bar) and summary tables per medicine type. |
| FR-13 | Demand forecast export | Export forecast reports as PDF/printable output. |

Implied/supporting requirements from the core-requirements checklist (not
separately tabled but required): role-based dashboards, user management (BHW
manages accounts), CRUD across all modules, notifications/system alerts,
general reports with date filtering/sorting/pagination and CSV/PDF export,
audit logs of sensitive actions, responsive desktop UI, and database
backup/recovery.

## 3. Non-Functional Requirements

| # | Attribute | Requirement |
|---|---|---|
| NFR-1 | Performance | GIS map loads in 3–5s; demand forecasting completes in ≤10s for up to 500 senior-citizen records. |
| NFR-2 | Security | Role-based access control: BHW full access, Midwife view-only (per scope below), IPHO limited to forecasting/reports. |
| NFR-3 | Usability | Simple, clearly labeled UI usable by staff with basic technical skills. |
| NFR-4 | Reliability | Accurate processing of medicine counts, beneficiary records, transactions, and SMS; no data loss. |
| NFR-5 | Scalability | Schema and design accommodate growth in beneficiaries, medicines, transactions, and geographic data without major restructuring. |
| NFR-6 | Availability | Available 8:00 AM–5:00 PM, Mon–Fri, with minimal downtime; recovery within 24 hours of failure. |
| NFR-7 | Response time | User actions (save/update/report) respond within 2s; SMS sent within 1 minute of schedule trigger. |
| NFR-8 | Accuracy | Forecasting accuracy ≥85% vs. actual consumption; inventory matches physical counts after each transaction. |
| NFR-9 | Maintainability | Modular code, documented, easy to extend with new reports or GIS features. |
| NFR-10 | Interoperability | Integrates with an SMS gateway (e.g., Semaphore) and a mapping API (e.g., OpenStreetMap/Leaflet.js). |
| NFR-11 | GIS map accuracy | Pins placed correctly within barangay boundaries; legend explains pin colors/icons. |
| NFR-12 | Forecast reliability | Forecast indicates the historical window used (e.g., past 1–2 months) so users can judge reliability. |

## 4. User Roles and Permissions

| Capability | BHW | Midwife | IPHO |
|---|:---:|:---:|:---:|
| Manage user accounts | ✅ | ❌ | ❌ |
| Manage system settings | ✅ | ❌ | ❌ |
| Manage senior-citizen profiles (add/edit/delete) | ✅ | ❌ | ❌ |
| View senior-citizen information | ✅ | ✅ | ❌ |
| Manage medicine inventory (add/edit/delete) | ✅ | ❌ | ❌ |
| View medicine inventory | ✅ | ✅ | ❌ |
| View / manage distribution schedules & transactions | ✅ (manage) | ✅ (view) | ❌ |
| View GIS mapping | ✅ | ✅ | ❌ |
| View demand forecasting | ✅ | ✅ | ✅ |
| Generate / export reports | ✅ | — (view only, per NFR-2) | ✅ (forecast reports) |
| View audit logs | ✅ | ❌ | ❌ |

Permissions are enforced on both the frontend (hide/disable UI) and the
backend (every API call re-checks role on the server; no endpoint trusts the
client). Direct URL/API access to a restricted resource returns `401/403`
regardless of what the UI exposes.

## 5. Main Modules

1. Authentication & session management (login/logout, password hashing, RBAC middleware)
2. User management (BHW-only: create/deactivate Midwife & IPHO accounts)
3. Senior citizen (beneficiary) profile management
4. Medicine inventory management (stock, batches, expiration, low-stock alerts)
5. Distribution scheduling (auto-generated on stock replenishment)
6. Distribution monitoring / transaction log
7. SMS notification service (triggered by schedule creation)
8. Search, filter, sort (cross-module)
9. GIS mapping & geo-filtering
10. Demand forecasting engine & visualization
11. Reports (operational + forecast), with PDF/CSV export
12. Dashboard (role-scoped widgets)
13. Audit logging
14. System settings (BHW-managed: e.g., low-stock defaults, SMS templates)

## 6. Recommended Technology Stack

Using the requested stack, refined for a maintainable build:

- **Frontend:** HTML5, CSS3, vanilla JavaScript (ES modules) organized as
  reusable components (no build tooling required), Chart.js for graphs,
  Leaflet.js + OpenStreetMap tiles for GIS.
- **Backend:** PHP 8.x, plain PDO for MySQL access (no ORM needed at this
  scale), a thin routing layer (either a minimal custom router or Slim
  Framework — see open question in §12) structured as a REST API.
- **Database:** MySQL 8.x, InnoDB, foreign keys + indexes per §7.
- **Auth:** Session-based auth (PHP sessions over HTTPS, `httponly` +
  `secure` + `SameSite=Strict` cookies) with `password_hash()`
  (bcrypt/argon2id). Session-based is preferred over JWT here since the
  entire app is server-rendered/AJAX from one origin — it avoids
  token-storage XSS risk and simplifies revocation (relevant for a health
  center handling sensitive PII).
- **SMS Gateway:** Semaphore (PH-focused, simple REST API) — API key stored
  in an environment variable, never in code.
- **Mapping:** Leaflet.js with OpenStreetMap tiles (free, no API key
  required).
- **PDF/CSV export:** Dompdf (PHP) for PDF; native CSV via `fputcsv`.

This stays within the requested stack; no deviation needs justification.

## 7. Database Entities (preview — finalized in Phase 4)

Core tables: `users`, `roles`, `senior_citizens`, `puroks`, `medicines`,
`medicine_batches`, `distribution_schedules`, `distributions`,
`sms_notifications`, `demand_forecasts`, `audit_logs`, `system_settings`.

Key relationships:
- `users.role_id → roles.id` (many-to-one)
- `senior_citizens.purok_id → puroks.id` (many-to-one)
- `medicine_batches.medicine_id → medicines.id` (one-to-many: one medicine, many batches — needed for expiration/FEFO tracking)
- `distribution_schedules.medicine_id → medicines.id`
- `distributions.schedule_id → distribution_schedules.id`,
  `distributions.senior_citizen_id → senior_citizens.id`,
  `distributions.medicine_batch_id → medicine_batches.id`,
  `distributions.bhw_id → users.id` (each many-to-one; a schedule has many
  distributions — one-to-many)
- `sms_notifications.senior_citizen_id → senior_citizens.id`,
  `sms_notifications.schedule_id → distribution_schedules.id`
- `demand_forecasts.medicine_id → medicines.id`
- `audit_logs.user_id → users.id`

Full DDL with types, indexes, and constraints is delivered in Phase 4.

## 8. Proposed Architecture

Three-tier, single-origin web app:

```
Browser (HTML/CSS/JS, Leaflet, Chart.js)
        │  fetch() → JSON over HTTPS
        ▼
PHP REST API (routing → controllers → services → PDO repositories)
   ├─ Auth/RBAC middleware (every route)
   ├─ Validation layer
   ├─ Audit-log writer (on mutating actions)
   ├─ SMS service (Semaphore client)
   └─ Forecast service (moving-average/exponential-smoothing job)
        ▼
MySQL 8.x (InnoDB)
```

Distribution-schedule creation and SMS dispatch are triggered synchronously
from the stock-update endpoint (call the SMS gateway inline, log failures to
`sms_notifications.status`); at this scale a queue is not required, but the
notification write is isolated in its own service so a queue can be added
later without touching business logic (see NFR-9 maintainability).

## 9. Main Pages/Screens

1. Login
2. Dashboard (role-scoped: BHW sees full stats; Midwife sees view-only
   summary; IPHO sees forecast-only summary)
3. Senior Citizens — list (search/filter/paginate) + add/edit modal + profile view
4. Medicine Inventory — list + add/edit modal + batch/expiration view
5. Distribution Schedules — list/calendar view, auto-created entries, manual status updates
6. Distribution Log — transaction list, record-distribution form
7. GIS Map — full-screen map with purok/condition/status filters and legend
8. Demand Forecasting — chart + table view, export
9. Reports — operational reports with date range, filters, export
10. User Management (BHW only)
11. Audit Logs (BHW only)
12. System Settings (BHW only)

## 10. API Requirements

REST, JSON, consistent envelope:
```json
{ "success": true, "message": "Operation completed successfully", "data": {} }
```
Every endpoint declares: method, path, auth requirement, required
role/permission, request params/body, response shape, validation rules, and
error cases. Representative subset (full list delivered in Phase 2):

| Method | Path | Auth | Role | Purpose |
|---|---|---|---|---|
| POST | /api/auth/login | none | — | Authenticate, start session |
| POST | /api/auth/logout | session | any | End session |
| GET | /api/senior-citizens | session | BHW, Midwife | List/search/filter |
| POST | /api/senior-citizens | session | BHW | Create beneficiary |
| PUT | /api/senior-citizens/{id} | session | BHW | Update beneficiary |
| DELETE | /api/senior-citizens/{id} | session | BHW | Deactivate/delete beneficiary |
| GET | /api/medicines | session | BHW, Midwife | List inventory |
| POST | /api/medicines | session | BHW | Create medicine |
| POST | /api/medicines/{id}/stock | session | BHW | Update stock → may trigger schedule + SMS |
| GET | /api/distribution-schedules | session | BHW, Midwife | List schedules |
| POST | /api/distributions | session | BHW | Record a distribution transaction |
| GET | /api/map/beneficiaries | session | BHW, Midwife | GIS pin data (filterable) |
| GET | /api/forecasts | session | BHW, Midwife, IPHO | Forecast per medicine |
| GET | /api/reports/{type} | session | role-dependent | Filtered/paginated report data |
| GET | /api/reports/{type}/export | session | role-dependent | PDF/CSV export |
| GET | /api/audit-logs | session | BHW | Audit trail |
| GET/POST | /api/users | session | BHW | User management |

Errors use HTTP status codes (400 validation, 401 unauthenticated, 403
unauthorized, 404 not found, 409 duplicate/conflict, 500 server) with a safe,
non-technical `message` and no stack traces or SQL text in the response body.

## 11. Security Considerations

- Passwords hashed with `password_hash()` (bcrypt/argon2id), never stored or logged in plaintext.
- All queries via PDO prepared statements (no string-concatenated SQL) — SQL-injection protection.
- Output escaping (`htmlspecialchars`) and a Content-Security-Policy header — XSS protection.
- CSRF tokens on all state-changing requests (double-submit or synchronizer token).
- Secure, `httponly`, `SameSite` session cookies; session regenerated on login; idle timeout.
- Server-side authorization check on every endpoint (never trust the frontend role check).
- Rate limiting on `/api/auth/login` (e.g., lockout/backoff after repeated failures) to resist brute force.
- File-upload validation (MIME + extension allowlist, size limit) if/when profile photos or attachments are added.
- Secrets (DB credentials, SMS API key) only in environment variables, never committed.
- Audit log entries for create/update/delete on senior-citizen records, inventory, user accounts, and login attempts.
- Generic, safe error messages to end users; details go to server logs only.

## 12. Development Roadmap

| Phase | Deliverable |
|---|---|
| 1 | Requirements analysis — **this document** |
| 2 | System architecture, finalized tech stack, module/data-flow diagrams, full API spec |
| 3 | UI/UX: page structure, navigation, wireframe-level layout for every screen in §9 |
| 4 | Database schema + migrations (DDL, seed data, ERD) |
| 5 | Backend: auth/RBAC, all module APIs, validation, SMS + forecast services |
| 6 | Frontend: all screens wired to the API |
| 7 | Tests: auth, RBAC, CRUD, validation, key business rules (stock-triggered scheduling, forecast accuracy check) |
| 8 | Deployment: environment setup, DB setup, server config, backup strategy |

Given the scope (13+ functional requirements across 4 roles, two third-party
integrations, and a forecasting engine), Phase 5–6 will be delivered
module-by-module rather than as one drop, per the requested process.

## 13. Assumptions and Open Questions

Reasonable assumptions made so far (flag if any is wrong):

1. **BHW = de facto administrator.** BHW has full CRUD + user management +
   settings; there is no separate "admin" role.
2. **Single health center, single deployment.** The system serves Barangay
   New Bulatukan only (not multi-barangay), so no barangay-selector is
   needed — only a purok selector within it.
3. **Purok list** is a fixed lookup table seeded with Barangay New
   Bulatukan's actual purok names — I don't have the real list, so Phase 4
   will seed placeholders (Purok 1–7) unless the real names are provided.
4. **Low-stock threshold** is configurable per medicine (a column on
   `medicines`), not a single global constant.
5. **"Available level" that triggers a schedule** = stock crosses back above
   its configured low-stock threshold after a stock-in event. Please
   confirm, or specify a different trigger rule (e.g., any stock increase,
   or a minimum absolute quantity).
6. **Distribution venue** defaults to the health center itself but is an
   editable field per schedule (not hardcoded), since the requirement lists
   it as a schedule attribute.
7. **Forecasting method**: given the modest data volume (≤500 beneficiaries,
   monthly cadence) a moving-average / exponential-smoothing model on
   monthly per-medicine distribution totals is proposed — simple, explainable
   to non-technical health workers, and tunable to hit the ≥85% accuracy
   target. A heavier ML model is not justified at this scale unless you
   want one.
8. **SMS gateway account**: Semaphore (or an equivalent PH SMS API) requires
   a paid account and API key that only you can provision — this will be
   read from an environment variable; a sandbox/mock sender will be used in
   development if no key is available yet.
9. **Mobile numbers** are assumed Philippine format (`09XXXXXXXXX` /
   `+639XXXXXXXXX`); validation will enforce this pattern.
10. **Record deletion** for senior citizens/medicines is assumed to be a soft
    delete (an `is_active`/`deleted_at` flag) rather than a hard delete, to
    preserve distribution history integrity — flag if hard delete is
    actually required.
11. **Hosting/deployment target** is not specified (school capstone demo vs.
    a real server at the health center). Phase 8 will assume a standard
    LAMP-style deployment (Apache/Nginx + PHP-FPM + MySQL) unless told
    otherwise.
12. **Midwife reports access**: the NFR table says Midwife has "view-only"
    access generally, but doesn't list report *generation*; the module
    checklist doesn't grant Midwife report export. I've scoped Midwife to
    view distribution/inventory/map/forecast data but **not** generate
    reports — confirm if Midwife should also export reports.

Please confirm or correct the assumptions above, and let me know how you'd
like to proceed on scope (see the follow-up question) — then Phase 2 onward
will proceed module by module.
