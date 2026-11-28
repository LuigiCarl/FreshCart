# Laravel 12 Architecture Patterns & Examples

## 1. Typed DTOs (`final readonly class`)

Replace untyped associative arrays passed between Form Requests, Controllers, and Actions with `final readonly` DTOs under `App\Data\` (or `App\DTOs\`).

```php
<?php

namespace App\Data;

final readonly class PlaceOrderData
{
    /**
     * @param  list<OrderItemData>  $items
     */
    public function __construct(
        public string $customerName,
        public string $customerEmail,
        public array $items,
        public ?string $idempotencyKey = null,
    ) {}
}
```

Convert inside the Form Request:

```php
public function toDto(): PlaceOrderData
{
    $validated = $this->validated();

    return new PlaceOrderData(
        customerName: $validated['customer_name'],
        customerEmail: $validated['customer_email'],
        items: array_map(
            fn (array $item): OrderItemData => new OrderItemData(
                productId: (int) $item['product_id'],
                quantity: (int) $item['quantity'],
            ),
            $validated['items'],
        ),
        idempotencyKey: $validated['idempotency_key'] ?? null,
    );
}
```

## 2. Consistent Route Model Binding + Policies

Prefer route model binding with Policy middleware over manual `where('user_id', ...)->firstOrFail()`:

```php
Route::get('/orders/{order:order_number}', [OrderController::class, 'show'])
    ->middleware('can:view,order')
    ->name('orders.show');

Route::post('/orders/{order:order_number}/cancel', [OrderController::class, 'cancel'])
    ->middleware('can:cancel,order')
    ->name('orders.cancel');
```

In `OrderPolicy`:

```php
public function view(User $user, Order $order): Response
{
    return $order->isOwnedBy($user)
        ? Response::allow()
        : Response::denyAsNotFound();
}

public function cancel(User $user, Order $order): Response
{
    return $this->view($user, $order);
}
```

Note: When an order is bound at the route layer, any subsequent write inside a transaction (like cancellation) should still re-fetch with `lockForUpdate()` inside `DB::transaction()` so state transitions remain atomic under concurrency.

## 3. Enum Domain Behavior

Put status rules directly on the backed enum (`App\Enums\OrderStatus`):

```php
enum OrderStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

    public function canBeCancelled(): bool
    {
        return $this === self::Pending;
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }
}
```

## 4. Money Value Object

Centralize integer-cent arithmetic in `App\Support\Money` instead of duplicating `toCents()` / `fromCents()` helpers inside Actions:

```php
<?php

namespace App\Support;

final readonly class Money
{
    private function __construct(public int $cents) {}

    public static function fromDecimal(string $amount): self
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');

        return new self(((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0'));
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function multiply(int $quantity): self
    {
        return new self($this->cents * $quantity);
    }

    public function add(self $other): self
    {
        return new self($this->cents + $other->cents);
    }

    public function toDecimal(): string
    {
        return intdiv($this->cents, 100).'.'.str_pad((string) ($this->cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
```

## 5. Safe Post-Commit Side Effects

When dispatching mail or jobs from inside `DB::transaction(...)`:

```php
DB::transaction(function () use ($order, $invoicePath): void {
    // ... lock and update order ...

    DB::afterCommit(function () use ($order): void {
        Mail::to($order->customer_email)->queue(new SendOrderConfirmation($order));
    });
}, attempts: 3);
```

This guarantees that if the transaction rolls back, no confirmation email or downstream job is ever dispatched.

## 6. Model Strictness in `AppServiceProvider`

```php
use Illuminate\Database\Eloquent\Model;

public function boot(): void
{
    Model::shouldBeStrict(! $this->app->isProduction());
}
```

Warning: Enabling `Model::shouldBeStrict()` will immediately surface any hidden N+1 lazy loading or mass-assignment mismatches in tests — always run the full test suite right after enabling it and fix any lazy-loading calls with explicit `with()` / `loadMissing()`.
