# MediTrack — Phase 4: Database Design

DDL: `database/schema.sql`. Seed data: `database/seed.sql`.

## Entity-Relationship Diagram (text form)

```
roles (1) ───< (M) users
puroks (1) ───< (M) senior_citizens
medicines (1) ───< (M) medicine_batches
medicines (1) ───< (M) senior_citizens          [assigned_medicine_id]
medicines (1) ───< (M) distribution_schedules
distribution_schedules (1) ───< (M) distributions
senior_citizens (1) ───< (M) distributions
medicine_batches (1) ───< (M) distributions      [FEFO dispensing]
users (1) ───< (M) distributions                 [bhw_id]
senior_citizens (1) ───< (M) sms_notifications
distribution_schedules (1) ───< (M) sms_notifications
medicines (1) ───< (M) demand_forecasts
users (1) ───< (M) audit_logs
```

No many-to-many relationships are required: a beneficiary has exactly one
assigned maintenance medicine at a time (matches FR-2's "assigned
maintenance medicine" as a single field), and a distribution transaction
always references exactly one schedule, one beneficiary, and one batch.

## Normalization notes

- 3NF: no repeating groups; `medicine_batches` is split out from `medicines`
  specifically to avoid duplicating medicine metadata per batch and to
  support FEFO (first-expiry-first-out) dispensing and per-batch expiration
  alerts.
- `puroks` is a lookup table rather than a free-text column, so GIS
  filtering/grouping by purok is exact rather than string-matched.
- `medicines.stock_quantity` is a maintained aggregate (sum of
  `medicine_batches.quantity_remaining`), updated transactionally on stock-in
  and on each distribution — kept as a column (denormalized on purpose) so
  list views and low-stock checks don't need to aggregate batches on every
  read; it always matches the batch sum because both are updated in the
  same DB transaction.
- `demand_forecasts` is a materialized cache of computed predictions (one
  row per medicine per forecasted month) rather than computed purely
  on-the-fly, so historical forecasts remain inspectable/exportable even as
  new distribution data comes in.

## Constraints

- Foreign keys on every relationship above (see `schema.sql`); referenced
  rows (`roles`, `puroks`, `medicines`) are not hard-deleted, only
  deactivated, so historical FK references never dangle.
- `users.username`, `medicines.name`, `puroks.name` unique.
- `medicine_batches` unique on `(medicine_id, batch_number)`.
- `demand_forecasts` unique on `(medicine_id, forecast_month)` — one
  forecast per medicine per month, regenerated (upsert) as new data arrives.
- Soft delete via `is_active` on `users`, `medicines`, `senior_citizens`
  (see Phase 1 §13.10) — distribution history stays valid after a record is
  retired.

## Indexes

Beyond primary/foreign keys: `senior_citizens(full_name)`,
`senior_citizens(purok_id)`, `senior_citizens(medical_condition)`,
`senior_citizens(distribution_status)`, `medicines(category)`,
`medicines(stock_quantity, low_stock_threshold)` (low-stock queries),
`medicine_batches(expiration_date)` (FEFO/expiration alerts),
`distribution_schedules(status)`, `distribution_schedules(scheduled_date)`,
`distributions(status)`, `distributions(senior_citizen_id)`,
`distributions(date_released)`, `sms_notifications(status)`,
`audit_logs(entity_type, entity_id)`, `audit_logs(created_at)` — these back
every search/filter/sort combination listed in the API spec (Phase 2 §5).
