# FreshCart Orders API

A Laravel 12 API for placing and tracking grocery orders. Checkout reserves stock in a database transaction; invoice generation and confirmation email are handled by queued jobs.

## Requirements

- PHP 8.2 or newer with `pdo_mysql`
- Composer
- MySQL 8

## Setup

1. Install PHP dependencies:

   ```powershell
   composer install
   ```

2. Copy the example environment file and generate an application key:

   ```powershell
   Copy-Item .env.example .env
   php artisan key:generate
   ```

3. Create a MySQL database named `freshcart`, then update `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` in `.env` if needed.

4. Run migrations and seed the sample user and products:

   ```powershell
   php artisan migrate --seed
   ```

5. Start the API:

   ```powershell
   php artisan serve
   ```

The API is available at `http://127.0.0.1:8000`. The example environment uses the database queue and the log mailer; no external mail server is required.

## Queue worker

Run a worker in a separate terminal to process invoices and confirmation emails:

```powershell
php artisan queue:work database --tries=3
```

Inspect or retry jobs that exhausted their attempts:

```powershell
php artisan queue:failed
php artisan queue:retry all
```

Order-processing failures set the order status to `failed` and record a reason. Email delivery is a separate queued job; if it fails, Laravel records it as a failed job while the already-processed order remains `completed`.

Invoices are written to `storage/app/invoices`.

## API endpoints

All endpoints are under `/api/v1` and return JSON.

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/api/v1/products` | Paginated product list (15 per page) |
| `POST` | `/api/v1/orders` | Place an order |
| `GET` | `/api/v1/orders` | Paginated order list; optional `status` and `customer_email` filters |
| `GET` | `/api/v1/orders/{order_number}` | Order details with item and product names |
| `POST` | `/api/v1/orders/{order_number}/cancel` | Cancel a pending order and restore its stock |

### Idempotent checkout

Optionally send an `Idempotency-Key` header with `POST /api/v1/orders`. Retrying the same request with the same key returns the existing order without decrementing stock or dispatching another processing job. Reusing the key with a different payload returns `409 Conflict`.

### cURL examples (PowerShell)

List products:

```powershell
curl.exe -H "Accept: application/json" "http://127.0.0.1:8000/api/v1/products"
```

Place an order using a `product_id` from the product list:

```powershell
curl.exe -X POST "http://127.0.0.1:8000/api/v1/orders" -H "Accept: application/json" -H "Content-Type: application/json" -H "Idempotency-Key: checkout-sam-001" --data-raw '{"customer_name":"Sam Shopper","customer_email":"sam@example.com","items":[{"product_id":1,"quantity":2}]}'
```

List pending orders for an email address:

```powershell
curl.exe -H "Accept: application/json" "http://127.0.0.1:8000/api/v1/orders?status=pending&customer_email=sam%40example.com"
```

Show an order or cancel it by replacing `{order_number}` with the returned order number:

```powershell
curl.exe -H "Accept: application/json" "http://127.0.0.1:8000/api/v1/orders/{order_number}"
curl.exe -X POST -H "Accept: application/json" "http://127.0.0.1:8000/api/v1/orders/{order_number}/cancel"
```

## Tests

Run the test suite with:

```powershell
php artisan test
```

Tests use an in-memory SQLite database. This keeps tests isolated from the configured MySQL database, but SQLite does not verify MySQL row-lock behavior under concurrent checkouts. Run concurrency checks against MySQL 8 before relying on them in production.

## Assumptions and behavior

- Checkout is unauthenticated; authentication is an optional assessment bonus.
- Product prices come from the database, never from client-supplied prices.
- Stock is locked and checked in a transaction; insufficient stock rolls back the entire order.
- Orders are created as `pending`. Invoice and email work is queued after checkout commits.
- Only `pending` orders can be cancelled. Cancellation and stock restoration happen atomically.
- API validation, not-found, and server errors use JSON responses with `message` and `errors` fields.

## AI disclosure

AI assistance was used for planning, implementation, and test design. The resulting changes were reviewed and tested during development.
