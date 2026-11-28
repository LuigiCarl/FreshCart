# FreshCart — Laravel 12 Engineering Rules

This repository is the **FreshCart Orders API**, a Laravel 12 REST API running on PHP 8.4 with Sanctum authentication, MySQL 8 (SQLite in-memory for tests), database queues, and PHPUnit 11.

These rules apply across the entire repository. For deep architectural patterns and checklists, load the `laravel-best-practices` skill. For multi-file refactoring, load the `laravel-refactor` skill.

---

## 1. Architecture & Separation of Concerns

- **Thin Controllers**: Controllers must only handle HTTP concerns (accept validated input, authorize, invoke an Action/Service, return a Resource/Response). Never put business logic, multi-step transactions, or raw validation arrays inside controllers.
- **Form Requests for Validation**: Every endpoint that accepts input (including query-string filters like `index`) must validate via a dedicated `FormRequest` under `App\Http\Requests\Api\V1\`.
- **Single-Purpose Actions**: Encapsulate domain workflows and state transitions in invokable or `execute()` Action classes under `App\Actions\` (or domain-grouped subdirectories when they grow). Actions must not depend on `Illuminate\Http\Request`.
- **Typed Data Transfer Objects (DTOs)**: Prefer `final readonly class` DTOs over untyped associative arrays when passing structured payloads between Controllers, Requests, and Actions.
- **Authorization via Policies & Route Model Binding**: Use explicit route model binding (e.g., `{order:order_number}`) paired with Policies (`App\Policies\`) and `can:` middleware or `$this->authorize()` instead of repeating `where('user_id', ...)` ownership checks in every controller method. Never leak resource existence to non-owners (use `Response::denyAsNotFound()`).
- **API Resources**: Always serialize API responses through `JsonResource` classes under `App\Http\Resources\`. Never return raw Eloquent models directly from controllers.

## 2. Laravel 12 & PHP 8.4 Standards

- **Strict PHP**: Use strict parameter and return types on all methods, typed class properties, constructor property promotion, `readonly` where applicable, and backed PHP `enum`s (`App\Enums\`) instead of magic strings.
- **Laravel 12 Conventions**:
  - Register middleware, routing, and exception rendering in `bootstrap/app.php` (do not recreate legacy `Http/Kernel.php` or `Exceptions/Handler.php`).
  - Define model casts using the `protected function casts(): array` method rather than the `$casts` property.
  - Prefer `Model::query()` over static magic calls (`Model::where(...)`) for static analysis clarity.
  - Use `config(...)` in application code; never call `env(...)` outside `config/*.php`.
- **Code Style**: Follow the `laravel` Pint preset (`vendor/bin/pint --test`).

## 3. Eloquent, Transactions & Concurrency Safety

- **No N+1 Queries**: Eager-load required relationships (`with(...)`, `loadMissing(...)`) before passing models to Resources or Jobs.
- **Atomic Transactions & Locking**:
  - Wrap stock reservation, order creation, and order cancellation in `DB::transaction(..., attempts: 3)`.
  - Lock rows in a deterministic order (`orderBy('id')->lockForUpdate()`) to prevent deadlocks and negative stock.
  - Dispatch queued jobs and mail **after commit** (`->afterCommit()` or `DB::afterCommit(...)`) so workers never observe uncommitted or rolled-back state.
- **Server-Authoritative Pricing**: Always read product prices from the database; never trust client-supplied prices or totals. Avoid floating-point arithmetic for money (use integer cents or `bcmath`).
- **Database Schema Safety**: Never edit already-merged migrations; create additive migrations with proper foreign keys and indexes.

## 4. Queues, Jobs & Failure Handling

- **Idempotent Jobs**: Every queued job must be safe to retry and must guard against duplicate execution (e.g., skip already `completed` or `cancelled` orders).
- **Explicit Retry Policy**: Define `$tries` and `$backoff` on jobs, and implement `failed(?Throwable $exception)` to record terminal failure state cleanly without overwriting completed/cancelled orders.
- **Non-Blocking Checkout**: Never generate invoices or send emails synchronously inside HTTP checkout requests.

## 5. API Contract & Error Handling

- **Consistent JSON Errors**: All `/api/v1/*` errors (`401`, `404`, `409`, `422`, `429`, `500`) must return JSON with `message` and `errors` keys as configured in `bootstrap/app.php`.
- **Backward Compatibility**: During refactors, preserve existing endpoint URIs, HTTP status codes, JSON response structures, and rate-limiting behavior unless explicitly asked to change them.

## 6. Testing & Verification

- **Feature + Unit Coverage**: Use PHPUnit 11 (`tests/Feature`, `tests/Unit`) with `RefreshDatabase`, model factories, and `Queue::fake()`, `Mail::fake()`, `Storage::fake()` where appropriate.
- **Required Checks Before Finishing**:
  1. Run focused tests, then the full suite: `php artisan test`
  2. Verify formatting: `vendor/bin/pint --test`
- **Security**: Never commit `.env`, secrets, or credentials. Keep uploaded files on private disks (`order_images`, `invoices`) and stream them only after authorization.
