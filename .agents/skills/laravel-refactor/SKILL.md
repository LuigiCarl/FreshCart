---
name: laravel-refactor
description: >-
  Step-by-step safe refactoring workflow for Laravel 12 API codebases. Use this skill
  when auditing and refactoring existing controllers, actions, models, policies, jobs,
  resources, or tests to align with Laravel best practices without breaking API contracts.
---

# Laravel Safe Refactoring Runbook

Follow this procedure whenever refactoring an existing Laravel codebase (full-codebase or module-level) so every change stays verifiable, reviewable, and backward-compatible.

## Prerequisites

1. Load the `laravel-best-practices` skill first so all target patterns are in context.
2. Establish a clean baseline before editing any application code:
   ```powershell
   git status --short
   php artisan test
   vendor/bin/pint --test
   ```
   Do **not** begin refactoring if tests are already failing without understanding why.

## Step 1: Audit the Codebase

Inspect the codebase systematically and group findings by layer:
- **Routes & Rate Limiting**: Named rate limiters in `AppServiceProvider`, consistent route model binding (`{order:order_number}`), policy middleware.
- **Controllers & Form Requests**: Inline `$request->validate(...)` in controllers, fat controller methods, missing `FormRequest` classes, missing typed DTOs.
- **Actions & Domain Services**: Oversized Actions (>100 lines), mixed responsibilities (e.g., idempotency + money math + invoice formatting + low-stock alerts all in one class), raw array payloads instead of DTOs.
- **Models & Eloquent**: Missing `Model::shouldBeStrict()`, duplicated query filters that belong in scopes, missing helper methods on Enums/Models, potential N+1 queries.
- **Jobs, Mail & Commands**: Missing `DB::afterCommit(...)` around `Mail::queue(...)`, formatting/business logic embedded inside jobs instead of dedicated services, commands duplicating Action logic.
- **Resources & Exceptions**: Consistent `{ message, errors }` JSON format, `whenLoaded()` usage, domain exception mapping.
- **Tests**: Missing unit tests for new value objects/services, coverage gaps in authorization, edge cases, or error formats.

Present a concise, prioritized refactor plan to the user before making sweeping edits across the codebase.

## Step 2: Refactor in Small, Safe Slices

Apply changes in dependency order, running `php artisan test` after each slice:

1. **Foundation & Support**: Value objects (`Money`), Enum methods (`OrderStatus`), named rate limiters, and `Model::shouldBeStrict()` (verify no lazy-loading regressions immediately).
2. **Requests & DTOs**: Extract missing `FormRequest` classes (e.g., `ListOrdersRequest`) and `readonly` DTOs (`App\Data\`).
3. **Policies & Routing**: Standardize route model binding and Policy authorization across all resource routes.
4. **Actions & Collaborators**: Split oversized Actions into focused collaborators (e.g., `InvoiceGenerator`, `LowStockAlertDispatcher`) while preserving exact transaction boundaries, deterministic `lockForUpdate()` ordering, and `afterCommit()` dispatches.
5. **Controllers & Resources**: Simplify controllers to thin HTTP adapters; verify `JsonResource` output matches the existing API contract byte-for-byte in structure and types.
6. **Jobs, Mail & Commands**: Clean up job responsibilities, ensure `DB::afterCommit(...)` wraps post-transaction mail/job dispatches, and keep console commands thin.
7. **Tests**: Add unit tests for extracted value objects/collaborators and update/add feature tests for any newly covered edge cases.

## Step 3: Final Verification Gate

Before concluding any refactor:

```powershell
php artisan test
vendor/bin/pint --test
git diff --stat
```

Confirm:
- All existing and new tests pass.
- `vendor/bin/pint --test` reports zero style issues.
- No unintended API contract changes (URIs, status codes, JSON keys, headers, rate limits).
- No `.env`, secrets, or unrelated files are modified.
