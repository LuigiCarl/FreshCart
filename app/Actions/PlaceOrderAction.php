<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Jobs\ProcessOrder;
use App\Jobs\SendLowStockAlert;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PlaceOrderAction
{
    private const LOW_STOCK_THRESHOLD = 5;

    /**
     * @param  array{customer_name: string, customer_email: string, items: array<int, array{product_id: int, quantity: int}>}  $data
     */
    public function execute(array $data, int $userId, ?string $idempotencyKey = null): Order
    {
        $requestHash = $idempotencyKey === null
            ? null
            : hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));

        if ($idempotencyKey !== null) {
            $existingOrder = Order::query()
                ->where('user_id', $userId)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existingOrder !== null) {
                return $this->resolveIdempotentOrder($existingOrder, $requestHash);
            }
        }

        try {
            $order = DB::transaction(function () use ($data, $userId, $idempotencyKey, $requestHash): Order {
                if ($idempotencyKey !== null) {
                    $existingOrder = Order::query()
                        ->where('user_id', $userId)
                        ->where('idempotency_key', $idempotencyKey)
                        ->lockForUpdate()
                        ->first();

                    if ($existingOrder !== null) {
                        return $this->resolveIdempotentOrder($existingOrder, $requestHash);
                    }
                }

                $quantities = [];

                foreach ($data['items'] as $item) {
                    $productId = (int) $item['product_id'];
                    $quantities[$productId] = ($quantities[$productId] ?? 0) + (int) $item['quantity'];
                }

                $products = Product::query()
                    ->whereKey(array_keys($quantities))
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                foreach ($quantities as $productId => $quantity) {
                    $product = $products->get($productId);

                    if ($product === null) {
                        throw ValidationException::withMessages([
                            'items' => "Product {$productId} is no longer available.",
                        ]);
                    }

                    if ($product->stock < $quantity) {
                        throw ValidationException::withMessages([
                            'items' => "Insufficient stock for {$product->name}. Available: {$product->stock}; requested: {$quantity}.",
                        ]);
                    }
                }

                $order = Order::query()->create([
                    'user_id' => $userId,
                    'order_number' => sprintf('FC-%s-%s', now()->format('Ymd'), Str::upper((string) Str::ulid())),
                    'customer_name' => $data['customer_name'],
                    'customer_email' => $data['customer_email'],
                    'idempotency_key' => $idempotencyKey,
                    'idempotency_request_hash' => $requestHash,
                    'status' => OrderStatus::Pending,
                    'total' => '0.00',
                ]);

                $totalCents = 0;

                foreach ($data['items'] as $item) {
                    $product = $products->get((int) $item['product_id']);
                    $quantity = (int) $item['quantity'];
                    $unitPrice = (string) $product->price;
                    $subtotalCents = $this->toCents($unitPrice) * $quantity;

                    $order->items()->create([
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'subtotal' => $this->fromCents($subtotalCents),
                    ]);

                    $totalCents += $subtotalCents;
                }

                foreach ($quantities as $productId => $quantity) {
                    $product = $products->get($productId);
                    $stockBefore = (int) $product->stock;
                    $remainingStock = $stockBefore - $quantity;

                    $product->decrement('stock', $quantity);

                    if ($stockBefore >= self::LOW_STOCK_THRESHOLD && $remainingStock < self::LOW_STOCK_THRESHOLD) {
                        $adminEmail = config('services.low_stock_alert_email');

                        if (! is_string($adminEmail) || trim($adminEmail) === '') {
                            Log::warning('Low-stock alert was not queued because LOW_STOCK_ALERT_EMAIL is not configured.', [
                                'product_id' => $product->id,
                                'remaining_stock' => $remainingStock,
                            ]);

                            continue;
                        }

                        SendLowStockAlert::dispatch(
                            $product->id,
                            $product->name,
                            $remainingStock,
                            trim($adminEmail),
                        )->afterCommit();
                    }
                }

                $order->update(['total' => $this->fromCents($totalCents)]);

                ProcessOrder::dispatch($order->getKey())->afterCommit();

                return $order;
            }, attempts: 3);
        } catch (QueryException $exception) {
            if ($idempotencyKey === null || ! $this->isIdempotencyKeyViolation($exception)) {
                throw $exception;
            }

            $existingOrder = Order::query()
                ->where('user_id', $userId)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existingOrder === null) {
                throw $exception;
            }

            return $this->resolveIdempotentOrder($existingOrder, $requestHash);
        }

        return $order->load('items.product');
    }

    private function isIdempotencyKeyViolation(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;

        return in_array($sqlState, ['23000', '23505'], true)
            && str_contains(strtolower($exception->getMessage()), 'idempotency_key');
    }

    private function resolveIdempotentOrder(Order $order, ?string $requestHash): Order
    {
        if ($requestHash === null || ! hash_equals((string) $order->idempotency_request_hash, $requestHash)) {
            throw new ConflictHttpException('This Idempotency-Key has already been used with a different request.');
        }

        return $order->load('items.product');
    }

    private function toCents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');

        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    private function fromCents(int $amount): string
    {
        return intdiv($amount, 100).'.'.str_pad((string) ($amount % 100), 2, '0', STR_PAD_LEFT);
    }
}
