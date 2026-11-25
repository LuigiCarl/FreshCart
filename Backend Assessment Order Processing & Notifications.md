## Laravel Backend Assessment: Order Processing & Notifications

## Overview

You need to build a small order-placement API in Laravel, backed by MySQL, with order processing pushed to a queue.

| Item |   | Detail |   |
| --- | --- | --- | --- |
| Timebox |   | 4–6 hours of work, submitted within 48 hours |   |
| Stack |   | PHP 8.2+, Laravel 12, MySQL 8, database queue driver |   |
| Allowed |   | Official docs, Google, Stack Overflow; AI tools allowed but must be disclosed |   |
| Deliverables |   | Git repo link, README with setup steps, Postman collection or example cURL calls |   |
| Interview |   | 10-minute walkthrough to explain your code |   |

The README must explain how to run migrations, seeders, the queue worker and the tests, plus any assumptions made.

## Scenario

FreshCart is a local grocery delivery business. Customers order through a mobile app, and the app team needs a backend API to place and track orders.

Today, placing an order is slow: the old system generates the invoice and sends the confirmation email inside the same request, so checkout takes 8–10 seconds and fails whenever the mail server is down. Stock also goes negative when two customers buy the last item at the same time.

The business wants:

- Checkout to respond in under 1 second, with invoice and email handled in the background.

- Stock never to drop below zero, even under concurrent orders.

- Customers and support staff to see an order's current status at any time.

- Failed background processing to be visible, not silently lost.

Your task is to build the Orders API that delivers this.

## Requirements

## 1. Database (MySQL)

Create migrations, models and relationships for these tables. Column names are suggestions; sensible changes are fine if explained in the README.


| Table |   | Key columns |   | Notes |   |
| --- | --- | --- | --- | --- | --- |
| products |   | id, sku (unique), name, price (decimal 10,2), stock (unsigned int), timestamps |   | Seed at least 10 products with a seeder and factory |   |
| orders |   | id, order_number (unique), customer_name, customer_email, status, total (decimal 10,2), invoice_path (nullable), processed_at (nullable), failure_reason (nullable), timestamps |   | Index on status and customer_email |   |
| order_items |   | id, order_id (FK), product_id (FK), quantity, unit_price (decimal 10,2), subtotal (decimal 10,2), timestamps |   | unit_price is copied from the product at order time |   |

Relationships: Order hasMany OrderItem, OrderItem belongsTo Product and Order. Order status values:

pending, processing, completed, failed, cancelled (use a PHP enum or class constants).

## 2. API endpoints

All routes live in routes/api.php under /api/v1, return JSON, and use API Resources for responses.

|   |   | Method Endpoint |   | Purpose |   |   |   |   |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| GET |   | /api/v1/products |   |   | List products with pagination (15 per page) |   |   |   |
| POST |   | /api/v1/orders |   | Place an order |   |   |   |   |
| GET |   | /api/v1/orders |   |   | List orders, paginated, filterable by status and |   |   |   |
|   |   |   |   | customer_email |   |   |   |   |
| GET |   | /api/v1/orders/{order_number} |   |   | Show one order with its items and product names |   |   |   |
| POST |   | /api/v1/orders/{order_number}/ca ncel |   | stock | Cancel an order (only while pending), and return |   |   |   |

## 3. Placing an order (POST /api/v1/orders)

- 1. Validate the request with a Form Request class: customer_name required, customer_email valid email, items array with 1–20 entries, each with an existing product_id and quantity 1–50.

- 2. Inside a database transaction, lock the product rows (lockForUpdate), check stock, decrement stock, create the order and its items, and calculate the total on the server (never trust a price from the client).

- 3. If any product lacks stock, roll back and return 422 with a clear message naming the product.

- 4. Generate a unique order_number such as FC-20261124-0001.

- 5. Save the order as pending, dispatch the ProcessOrder job after the transaction commits, and return 201 with the order resource.

## 4. Queues and jobs

Use the database queue driver so reviewers can run it with only MySQL.


- ProcessOrder job: sets status to processing, generates a simple invoice (a text, HTML or PDF file in storage/app/invoices), saves invoice_path, sets status to completed and processed_at, then dispatches the email.

- SendOrderConfirmation: a queued Mailable (or Notification) sent to customer_email. Use the log mail driver so no real mail server is needed.

- Set \$tries = 3 and a backoff (for example 10, 30, 60 seconds) on ProcessOrder.

- Implement the failed() method: set status to failed and store the failure_reason.

- Jobs must be safe to run twice: if the order is already completed, the job does nothing.

## 5. General

- Consistent JSON error format for 404, 422 and 500 responses.

- Business logic in a service or action class (for example PlaceOrderAction), not in the controller.

- No secrets committed; provide .env.example.

- At least 5 feature tests (PHPUnit or Pest), using Queue::fake() and Mail::fake() where useful.

## Bonus tasks (optional)

Bonus work earns up to 10 extra points but never makes up for missing core requirements.

- Authentication: protect order endpoints with Laravel Sanctum tokens; customers see only their own orders.

- Low-stock alert: a queued job that emails an admin when a product's stock falls below 5.

- Idempotency: accept an Idempotency-Key header so a retried checkout does not create a duplicate order.

- Scheduled cleanup: a scheduled command that cancels orders stuck in pending for over 30 minutes.

- Rate limiting: limit POST /orders to 10 requests per minute per IP.

- Docker: a docker-compose.yml (or Laravel Sail) that runs app, MySQL and the queue worker together.
