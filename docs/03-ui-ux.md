# MediTrack — Phase 3: UI/UX Design

## 1. Layout Shell

Every authenticated page shares one shell:

```
┌─────────────────────────────────────────────────────────┐
│ Top header: MediTrack logo | page title | user name ▾    │
│ (▾ menu: profile, logout)                                 │
├───────────┬─────────────────────────────────────────────┤
│ Sidebar   │  Page content                                │
│ (role-    │                                               │
│  scoped   │                                               │
│  nav)     │                                               │
└───────────┴─────────────────────────────────────────────┘
```

Sidebar items shown per role (hidden, not just disabled, for roles without
access — the backend still enforces this independently):

- **BHW:** Dashboard, Senior Citizens, Medicine Inventory, Distribution
  Schedules, Distribution Log, GIS Map, Demand Forecasting, Reports, User
  Management, Audit Logs, Settings.
- **Midwife:** Dashboard, Senior Citizens (view), Medicine Inventory (view),
  Distribution Schedules (view), Distribution Log (view), GIS Map, Demand
  Forecasting.
- **IPHO:** Dashboard, Demand Forecasting, Reports (forecast export only).

## 2. Page Specs

### 2.1 Login
- Centered card: username, password, "Sign in" button.
- Inline validation (required fields); generic "Invalid username or
  password" on failure (no user enumeration).
- Loading spinner on submit; locked out message after repeated failures.

### 2.2 Dashboard
Role-scoped summary cards + widgets:
- **BHW:** total active beneficiaries, low-stock medicine count, upcoming
  distributions this week, pending SMS failures, recent activity feed
  (last 10 audit entries), quick actions ("Add beneficiary", "Update stock").
- **Midwife:** same stat cards minus user/audit data, read-only.
- **IPHO:** forecast summary cards (medicines trending up/down) + link to
  Demand Forecasting.
Empty state: "No data yet" illustration/text when a widget has nothing to
show (e.g., before the first stock update).

### 2.3 Senior Citizens
- Table: Name, Purok, Condition, Assigned Medicine, Distribution Status,
  Actions (BHW: edit/delete; Midwife: view only).
- Search box (name/mobile) + filters (purok, condition, status) + pagination.
- "Add Beneficiary" (BHW) opens a modal form: full name, birthdate (date
  picker), gender, purok (select), address detail, mobile number, guardian
  name/contact, medical condition (select), assigned medicine (select).
- Row click → profile view (read-only detail + distribution history for
  that person).
- Delete → confirmation dialog ("This will deactivate the beneficiary
  record. Distribution history is preserved. Continue?").
- Validation: required fields, PH mobile format, no future birthdate.

### 2.4 Medicine Inventory
- Table: Name, Category, Stock Qty, Unit, Low-Stock flag (badge), Nearest
  Expiration, Actions.
- Low-stock rows visually flagged (badge/row tint) — not color alone
  (icon + text, for accessibility).
- "Add Medicine" modal: name, category, description, unit, low-stock
  threshold.
- "Update Stock" modal (separate from edit): quantity received, batch
  number, expiration date, date received → submits to
  `POST /medicines/{id}/stock`; on success shows whether a distribution
  schedule + SMS batch was triggered.
- Batch/expiration sub-view per medicine (table of batches, FEFO order).

### 2.5 Distribution Schedules
- List/calendar toggle. Columns: Medicine, Scheduled Date, Time Slot,
  Venue, Status (Upcoming/Ongoing/Completed/Cancelled badge).
- BHW can edit date/time/venue/status before the schedule occurs.
- Auto-created badge ("Auto-generated") distinguishes system-created rows.

### 2.6 Distribution Log
- Table: Date, Beneficiary, Medicine, Qty Issued, Receiver, BHW in Charge,
  Status.
- "Record Distribution" (BHW): select schedule → select beneficiary
  (searchable) → medicine batch auto-suggested (FEFO) → quantity → receiver
  name → status.
- Filters: date range, beneficiary, medicine, status.

### 2.7 GIS Map
- Full-width Leaflet map centered on Barangay New Bulatukan.
- Filter bar: purok, medical condition, distribution status (multi-select).
- Legend: pin color by medical condition (e.g., red = hypertension, blue =
  diabetes, purple = both), pin shape/badge for distribution status.
- Click pin → popup card: name, condition, assigned medicine, distribution
  status, purok.
- Empty state if no beneficiaries match filters.

### 2.8 Demand Forecasting
- Medicine selector + "months back" selector (default 2).
- Bar/line chart: historical monthly totals vs. predicted next-month
  quantity.
- Table below chart: medicine, avg. historical demand, predicted quantity,
  confidence note (window used, per NFR-12).
- "Export PDF" button (role-permitted per Phase 2 §5).

### 2.9 Reports
- Report type selector (Inventory, Distribution).
- Date range picker, filters, sortable/paginated table.
- "Print" (browser print-friendly view), "Export CSV", "Export PDF".

### 2.10 User Management (BHW only)
- Table: Username, Full Name, Role, Status, Actions.
- Add/Edit modal: username, full name, role (select), temp password
  (auto-generated, shown once) or manual set.
- Deactivate confirmation dialog (soft delete; login blocked, history kept).

### 2.11 Audit Logs (BHW only)
- Table: Timestamp, User, Action, Entity, Details. Filters: user, action
  type, date range. Read-only, paginated.

### 2.12 Settings (BHW only)
- Default low-stock threshold, SMS message templates, distribution venue
  default — editable, saved to `system_settings`.

## 3. Shared UI Components

- **Table** with client-requested server-side pagination (page/per_page
  params), column sort toggle, empty-state row.
- **Modal/dialog** for all create/edit forms; **confirmation dialog** for
  every destructive action (delete/deactivate/cancel).
- **Toast notifications** for success/error feedback (auto-dismiss,
  screen-reader announced via `aria-live`).
- **Form validation**: inline field errors on blur + on submit; server
  validation errors mapped back to the same fields.
- **Loading state**: skeleton rows / spinner on any async fetch.
- **Error state**: retry button + safe message on failed fetch (never raw
  error/stack text).

## 4. Accessibility & Responsive Notes

- Desktop-first (per NFR scope), minimum supported width 1280px; layout
  reflows (sidebar collapses to icons) down to 1024px for tablets used at
  the health center.
- Color is never the sole indicator (status badges pair color with text/icon).
- All interactive elements keyboard-reachable; visible focus outline.
- Minimum body text 14px, WCAG AA contrast on the palette.

Phase 4 (database schema) follows next.
