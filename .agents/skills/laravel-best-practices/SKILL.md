---
name: laravel-best-practices
description: >-
  Laravel 12 and PHP 8.4 engineering best practices for API architecture, Eloquent,
  transactions, queues, authorization, validation, and testing. Use this skill when
  designing new Laravel features, reviewing code quality, auditing a Laravel codebase,
  or establishing architectural conventions before refactoring.
---

# Laravel 12 Best Practices

Use this skill as the authoritative engineering reference when building, reviewing, or refactoring Laravel 12 APIs in this repository.

## Core Request Lifecycle

Follow a clean, unidirectional flow for every API endpoint:

```text
Route (middleware + route model binding + policy gate)
  -> FormRequest (validation + typed DTO conversion)
    -> Controller (thin HTTP adapter)
      -> Action / Service (domain logic + DB::transaction + afterCommit dispatches)
        -> Model / Query Scope / Value Object
          -> JsonResource (response serialization)
```

## Key Principles Checklist

### 1. Routing & Authorization
- Group versioned endpoints under `/api/v1` with named routes (`api.v1.*`).
- Use **route model binding** with explicit route keys (e.g., `{order:order_number}`) consistently across all resource endpoints instead of manual `firstOrFail()` lookups in controllers.
- Centralize ownership and capability checks in **Policies** (`App\Policies\`) using `Response::denyAsNotFound()` when resource existence must not be leaked to non-owners.
- Register named rate limiters in `AppServiceProvider::boot()` (e.g., `throttle:checkout`, `throttle:auth`, `throttle:order-images`) instead of scattering magic `throttle:10,1` strings across routes.

### 2. Controllers & Form Requests
- Keep controllers thin and single-purpose (standard REST verbs or invokable controllers).
- Move **all** request validation — including query-string filters on `index` endpoints — into dedicated `FormRequest` classes under `App\Http\Requests\Api\V1\`.
- Add helper methods (e.g., `toDto()`) on Form Requests to produce strongly typed `readonly` DTOs so Actions never parse raw request arrays.

### 3. Actions, Domain Services & Value Objects
- One Action class = one business use case (`PlaceOrderAction`, `CancelOrderAction`, `UploadOrderImageAction`).
- Keep Action methods short and readable by extracting cohesive private helpers or focused domain collaborators (e.g., `IdempotencyGuard`, `Money` value object, `InvoiceGenerator`, `LowStockNotifier`) when an Action grows beyond ~80–100 lines or mixes multiple responsibilities.
- Pass domain models or typed DTOs into Actions rather than raw `array` payloads and loose scalar IDs where a `User` or `Order` model is already available.
- Never let Actions import or depend on `Illuminate\Http\Request`.

### 4. Models, Eloquent & Queries
- Enable strict Eloquent guards in non-production environments (`Model::shouldBeStrict(! $this->app->isProduction())`) to catch N+1 lazy loading, silently discarded attributes, and missing attribute accesses early.
- Define casts via `protected function casts(): array` (Laravel 12 convention).
- Put reusable query constraints in local query scopes (`scopeForUser`, `scopePendingOlderThan`, `scopeFilter`) or custom Eloquent builders rather than duplicating `where(...)` chains across controllers, actions, and console commands.
- Keep state-transition checks on the Enum or Model (e.g., `OrderStatus::canBeCancelled()`, `OrderStatus::isTerminal()`, `$order->isPending()`).

### 5. Transactions, Locking & Concurrency
- Wrap multi-table writes and inventory mutations in `DB::transaction(..., attempts: 3)`.
- Always lock rows in deterministic primary-key order (`orderBy('id')->lockForUpdate()`) to prevent deadlocks.
- Always defer side effects (queued jobs, mail, file cleanup of replaced assets) until after commit using `->afterCommit()` or `DB::afterCommit(...)`.
- Never use floating-point math for money; encapsulate integer-cent arithmetic in a dedicated `Money` value object (e.g., `App\Support\Money`).

### 6. Jobs, Mail & Scheduled Commands
- Jobs must be **idempotent** and guard against terminal states (`Completed`, `Cancelled`).
- Always declare `$tries`, `$backoff`, and `failed(?Throwable $exception)` on queued jobs.
- Keep `Mail::to(...)->queue(...)` inside `DB::afterCommit(...)` (or a dedicated post-commit dispatch) so emails are never queued if a transaction rolls back.
- Keep Artisan commands thin: delegate domain logic to the same Action classes used by HTTP controllers (e.g., `CleanupPendingOrders` calling `CancelOrderAction`).

### 7. Exception Handling & API Resources
- Centralize API exception rendering in `bootstrap/app.php` with a consistent `{ "message": ..., "errors": ... }` envelope.
- Prefer domain exceptions (e.g., `InsufficientStockException`, `OrderCannotBeCancelledException`, `IdempotencyConflictException`) or Laravel's `ValidationException` / HTTP exceptions cleanly mapped in `bootstrap/app.php`.
- Always return `JsonResource` / `AnonymousResourceCollection`, using `$this->whenLoaded(...)` to avoid accidental N+1 queries.

## Detailed Reference Guides

Read these references on demand when implementing or auditing specific areas:
- [Architecture Patterns & Code Examples](./references/architecture-patterns.md)
- [Testing & Verification Standards](./references/testing-and-verification.md)
