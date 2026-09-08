# MediTrack — Phase 2: System Architecture

Builds on the approved Phase 1 requirements analysis (`01-requirements-analysis.md`).
Assumptions from Phase 1 §13 are accepted as-is for this build.

## 1. Technology Stack (final)

| Layer | Choice | Notes |
|---|---|---|
| Frontend | HTML5, CSS3, vanilla JS (ES modules) | No build step; component-style JS modules under `frontend/js/components/` |
| Charts | Chart.js (CDN) | Demand forecast + dashboard charts |
| Maps | Leaflet.js + OpenStreetMap tiles (CDN) | No API key required |
| Backend | PHP 8.x | Custom lightweight router (no framework dependency to install) |
| DB access | PDO (MySQL) with prepared statements | No ORM |
| Database | MySQL 8.x / InnoDB | |
| Auth | PHP native sessions | `httponly`, `secure`, `SameSite=Strict` cookies; `password_hash()`/`password_verify()` |
| PDF export | Dompdf (Composer) | |
| SMS | Semaphore REST API | API key via `SEMAPHORE_API_KEY` env var |
| Config | `.env` file (loaded by a tiny parser, not committed) | `.env.example` committed instead |

## 2. Repository / Folder Structure

```
meditrack/
├── backend/
│   ├── public/
│   │   └── index.php              # single entry point / front controller
│   ├── src/
│   │   ├── Config/                # env loader, DB connection factory
│   │   ├── Core/                  # Router, Request, Response, Middleware
│   │   ├── Middleware/            # AuthMiddleware, RoleMiddleware, CsrfMiddleware
│   │   ├── Controllers/           # one per module (AuthController, SeniorCitizenController, ...)
│   │   ├── Services/              # SmsService, ForecastService, AuditLogService, ReportService
│   │   ├── Repositories/          # DB access per entity (PDO)
│   │   └── Validation/            # Validator helper
│   ├── tests/                     # PHPUnit
│   ├── composer.json
│   └── .env.example
├── frontend/
│   ├── index.html                 # login
│   ├── pages/                     # dashboard.html, senior-citizens.html, ...
│   ├── js/
│   │   ├── api.js                 # fetch wrapper (adds CSRF header, handles envelope/errors)
│   │   ├── auth.js
│   │   └── components/            # table.js, modal.js, pagination.js, toast.js, ...
│   └── css/
│       └── styles.css
├── database/
│   ├── schema.sql                 # full DDL
│   └── seed.sql                   # roles, demo puroks, demo admin user
└── docs/
    ├── 01-requirements-analysis.md
    ├── 02-architecture.md
    ├── 03-ui-ux.md
    ├── 04-database.md
    └── 08-deployment.md
```

## 3. Request Flow

```
Browser fetch()
   → backend/public/index.php (front controller)
       → Router matches method+path
       → Middleware chain: CORS/JSON → Auth (session check) → Role (permission check) → CSRF (mutating requests)
       → Controller: validates input (Validation\Validator), calls Service/Repository
       → Repository: PDO prepared statement
       → Controller wraps result in the standard envelope, sets HTTP status
       → AuditLogService writes a row for mutating actions on sensitive entities
   ← JSON response
```

Stock-update flow (FR-3/FR-4/FR-6), the one non-trivial business rule:

```
POST /api/medicines/{id}/stock  (BHW only)
  → MedicineRepository::adjustStock()      (transaction: update medicines.stock_quantity,
                                             insert medicine_batches row if a new batch)
  → if new_quantity crosses back above low_stock_threshold:
        → DistributionScheduleRepository::create(status = "Upcoming")
        → SmsService::notifyBeneficiariesForMedicine(medicine_id, schedule)
              → looks up senior_citizens whose assigned_medicine_id = medicine_id
              → sends SMS via Semaphore per beneficiary
              → writes one sms_notifications row per recipient (status: sent/failed)
  → AuditLogService::log('stock_update', ...)
  → response includes whether a schedule was created
```

This keeps the trigger logic inside `MedicineService` (not the controller), so it
is unit-testable without HTTP.

## 4. RBAC Enforcement

- `roles` table: `bhw`, `midwife`, `ipho`.
- Every route declares an `allowedRoles` array in the route table (`Core/Router`).
- `AuthMiddleware` rejects unauthenticated requests with `401`.
- `RoleMiddleware` rejects requests from a role not in `allowedRoles` with `403`,
  regardless of the URL — there is no route reachable by manually typing a URL
  that isn't also role-checked server-side.
- Frontend additionally hides/disables nav items and buttons per role (UX only,
  never the actual guard).

## 5. API Structure (complete)

Base path: `/api`. All responses use:
```json
{ "success": true|false, "message": "string", "data": {} }
```
Validation errors return `success:false`, HTTP 400, and `data.errors` as a
field→message map.

### Auth
| Method | Path | Auth | Roles | Body | Notes |
|---|---|---|---|---|---|
| POST | /auth/login | none | — | `{username, password}` | rate-limited (5 attempts / 15 min / IP+username) |
| POST | /auth/logout | session | any | — | destroys session |
| GET | /auth/me | session | any | — | current user + role |

### Users (BHW only)
| Method | Path | Roles | Body |
|---|---|---|---|
| GET | /users | bhw | query: `search, role, page, per_page` |
| POST | /users | bhw | `{username, password, full_name, role}` |
| PUT | /users/{id} | bhw | `{full_name, role, is_active}` |
| DELETE | /users/{id} | bhw | — (deactivate) |

### Senior Citizens
| Method | Path | Roles | Body/Query |
|---|---|---|---|
| GET | /senior-citizens | bhw, midwife | `search, purok_id, medical_condition, distribution_status, page, per_page, sort` |
| GET | /senior-citizens/{id} | bhw, midwife | — |
| POST | /senior-citizens | bhw | full_name, birthdate, gender, purok_id, address_detail, mobile_number, guardian_name, guardian_contact, medical_condition, assigned_medicine_id |
| PUT | /senior-citizens/{id} | bhw | same fields |
| DELETE | /senior-citizens/{id} | bhw | soft delete |

### Medicines
| Method | Path | Roles | Body/Query |
|---|---|---|---|
| GET | /medicines | bhw, midwife | `search, category, low_stock_only, page, per_page` |
| GET | /medicines/{id} | bhw, midwife | — |
| POST | /medicines | bhw | name, category, description, unit, low_stock_threshold |
| PUT | /medicines/{id} | bhw | same fields |
| DELETE | /medicines/{id} | bhw | soft delete |
| POST | /medicines/{id}/stock | bhw | `{quantity, batch_number, expiration_date, date_received}` → may trigger schedule+SMS |

### Distribution Schedules
| Method | Path | Roles | Body/Query |
|---|---|---|---|
| GET | /distribution-schedules | bhw, midwife | `status, medicine_id, date_from, date_to, page, per_page` |
| PUT | /distribution-schedules/{id} | bhw | `{status, scheduled_date, time_slot, venue}` |

### Distributions (transactions)
| Method | Path | Roles | Body/Query |
|---|---|---|---|
| GET | /distributions | bhw, midwife | `senior_citizen_id, medicine_id, status, date_from, date_to, page, per_page` |
| POST | /distributions | bhw | `{schedule_id, senior_citizen_id, medicine_batch_id, quantity_issued, receiver_name, status}` |
| PUT | /distributions/{id} | bhw | `{status}` |

### GIS Map
| Method | Path | Roles | Query |
|---|---|---|---|
| GET | /map/beneficiaries | bhw, midwife | `purok_id, medical_condition, distribution_status` → `[{id, name, lat, lng, purok, medical_condition, assigned_medicine, distribution_status}]` |

### Demand Forecasting
| Method | Path | Roles | Query |
|---|---|---|---|
| GET | /forecasts | bhw, midwife, ipho | `medicine_id, months_back (default 2)` |
| GET | /forecasts/export | bhw, midwife, ipho | same, returns PDF |

### Reports
| Method | Path | Roles | Query |
|---|---|---|---|
| GET | /reports/inventory | bhw, midwife | date_from, date_to, page, per_page |
| GET | /reports/distribution | bhw, midwife | date_from, date_to, page, per_page |
| GET | /reports/{type}/export | bhw (all types), midwife (view export not permitted per Phase 1 §13.12), ipho (forecast only) | `format=pdf|csv` |

### Dashboard / Audit
| Method | Path | Roles | Notes |
|---|---|---|---|
| GET | /dashboard | any | payload scoped by role |
| GET | /audit-logs | bhw | `user_id, action, date_from, date_to, page, per_page` |

## 6. Error Codes

| HTTP | Meaning |
|---|---|
| 400 | Validation failure (`data.errors`) |
| 401 | Not authenticated |
| 403 | Authenticated but role not permitted |
| 404 | Resource not found |
| 409 | Duplicate (e.g., username taken) |
| 429 | Rate limited (login) |
| 500 | Unexpected server error (generic message only; details in server logs) |

Phase 3 (UI/UX) and Phase 4 (database DDL) follow next.
