<?php

namespace App\Actions;

use App\Data\PlaceOrderData;
use App\Enums\OrderStatus;
use App\Jobs\ProcessOrder;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\LowStockAlertDispatcher;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PlaceOrderAction
{
    public function __construct(
        private readonly LowStockAlertDispatcher $lowStockAlerts,
    ) {}

    public function execute(User $user, PlaceOrderData $data): Order
    {
        $userId = (int) $user->getAuthIdentifier();
        $idempotencyKey = $data->idempotencyKey;
        $requestHash = $idempotencyKey === null ? null : $data->requestHash();

        if ($idempotencyKey !== null) {
            $existingOrder = $this->findIdempotentOrder($userId, $idempotencyKey);

            if ($existingOrder !== null) {
                return $this->resolveIdempotentOrder($existingOrder, $requestHash);
            }
        }

        try {
            $order = DB::transaction(function () use ($userId, $data, $idempotencyKey, $requestHash): Order {
                if ($idempotencyKey !== null) {
                    $existingOrder = $this->findIdempotentOrder($userId, $idempotencyKey, lockForUpdate: true);

                    if ($existingOrder !== null) {
                        return $this->resolveIdempotentOrder($existingOrder, $requestHash);
                    }
                }

                $quantities = $data->aggregatedQuantities();
                $products = $this->lockAndValidateProducts($quantities);

                $order = Order::query()->create([
                    'user_id' => $userId,
                    'order_number' => sprintf('FC-%s-%s', now()->format('Ymd'), Str::upper((string) Str::ulid())),
                    'customer_name' => $data->customerName,
                    'customer_email' => $data->customerEmail,
                    'idempotency_key' => $idempotencyKey,
                    'idempotency_request_hash' => $requestHash,
                    'status' => OrderStatus::Pending,
                    'total' => Money::zero()->toDecimal(),
                ]);

                $total = $this->createOrderItems($order, $data, $products);
                $this->decrementStockAndNotify($quantities, $products);

                $order->update(['total' => $total->toDecimal()]);

                ProcessOrder::dispatch($order->getKey())->afterCommit();

                return $order;
            }, attempts: 3);
        } catch (QueryException $exception) {
            if ($idempotencyKey === null || ! $this->isIdempotencyKeyViolation($exception)) {
                throw $exception;
            }

            $existingOrder = $this->findIdempotentOrder($userId, $idempotencyKey);

            if ($existingOrder === null) {
                throw $exception;
            }

            return $this->resolveIdempotentOrder($existingOrder, $requestHash);
        }

        return $order->loadMissing('items.product');
    }

    private function findIdempotentOrder(int $userId, string $idempotencyKey, bool $lockForUpdate = false): ?Order
    {
        $query = Order::query()
            ->where('user_id', $userId)
            ->where('idempotency_key', $idempotencyKey);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /**
     * @param  array<int, int>  $quantities
     * @return Collection<int, Product>
     */
    private function lockAndValidateProducts(array $quantities): Collection
    {
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

        return $products;
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    private function createOrderItems(Order $order, PlaceOrderData $data, Collection $products): Money
    {
        $total = Money::zero();

        foreach ($data->items as $item) {
            /** @var Product $product */
            $product = $products->get($item->productId);
            $unitPrice = (string) $product->price;
            $subtotal = Money::fromDecimal($unitPrice)->multiply($item->quantity);

            $order->items()->create([
                'product_id' => $product->id,
                'quantity' => $item->quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal->toDecimal(),
            ]);

            $total = $total->add($subtotal);
        }

        return $total;
    }

    /**
     * @param  array<int, int>  $quantities
     * @param  Collection<int, Product>  $products
     */
    private function decrementStockAndNotify(array $quantities, Collection $products): void
    {
        foreach ($quantities as $productId => $quantity) {
            /** @var Product $product */
            $product = $products->get($productId);
            $stockBefore = (int) $product->stock;
            $remainingStock = $stockBefore - $quantity;

            $product->decrement('stock', $quantity);

            $this->lowStockAlerts->dispatchIfThresholdCrossed($product, $stockBefore, $remainingStock);
        }
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

        return $order->loadMissing('items.product');
    }
}
