# Testing & Verification Standards (PHPUnit 11 + Laravel 12)

## Running Tests & Quality Checks

Run these commands from the repository root:

```powershell
# Full test suite (uses SQLite in-memory via phpunit.xml)
php artisan test

# Focused test file or filter
php artisan test --filter=OrderCheckoutApiTest

# Code style check (Laravel Pint)
vendor/bin/pint --test

# Auto-fix code style
vendor/bin/pint
```

## Feature Test Guidelines

- Use `RefreshDatabase` for database isolation.
- Authenticate via `Sanctum::actingAs($user)` for protected endpoints.
- Use `Queue::fake()`, `Mail::fake()`, and `Storage::fake('invoices')` / `Storage::fake('order_images')` to keep tests fast, deterministic, and isolated from disk/external services.
- Assert **both** HTTP contract (status code, JSON structure/values) and **side effects** (database state, stock counts, queued jobs/mail, stored/deleted files).
- Always test negative paths:
  - Unauthenticated (`401`)
  - Non-owner access (`404` via `denyAsNotFound()`)
  - Validation errors (`422` with `message` and `errors`)
  - Insufficient stock rollback (no partial order created, stock unchanged, no jobs dispatched)
  - Idempotency replay (`201`/same order, no double deduction) and conflict (`409`)
  - Rate-limit boundary (`429`)
  - Job retries, idempotent skip on completed/cancelled orders, and `failed()` state recording

## Unit Test Guidelines

- Add focused unit tests in `tests/Unit/` for pure value objects, enums, and helper services (e.g., `MoneyTest`, `OrderStatusTest`, `InvoiceGeneratorTest`) that do not need a database or HTTP kernel.

## Concurrency Caveat

- SQLite in-memory tests validate transactional rollback and application logic, but do **not** exercise MySQL 8 row-level `SELECT ... FOR UPDATE` contention.
- When changing locking queries (`lockForUpdate()`, `orderBy('id')`, idempotency unique-constraint recovery), verify against MySQL 8 (e.g., via Docker Compose) before shipping to production.
