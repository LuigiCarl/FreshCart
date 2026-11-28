<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CancelOrderAction
{
    public function execute(Order $order): Order
    {
        return $this->cancel($order->getKey(), skipNonPending: false)
            ?? throw new RuntimeException('User cancellation did not return an order.');
    }

    public function cancelIfPending(Order $order): ?Order
    {
        return $this->cancel($order->getKey(), skipNonPending: true);
    }

    private function cancel(int $orderId, bool $skipNonPending): ?Order
    {
        return DB::transaction(function () use ($orderId, $skipNonPending): ?Order {
            $order = Order::query()
                ->lockForUpdate()
                ->findOrFail($orderId);

            if (! $order->status->canBeCancelled()) {
                if ($skipNonPending) {
                    return null;
                }

                throw ValidationException::withMessages([
                    'status' => 'Only pending orders can be cancelled.',
                ]);
            }

            $quantities = [];

            foreach ($order->items()->orderBy('product_id')->orderBy('id')->get() as $item) {
                $quantities[$item->product_id] = ($quantities[$item->product_id] ?? 0) + $item->quantity;
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
                    throw new RuntimeException("Product {$productId} for order {$order->order_number} no longer exists.");
                }

                $product->increment('stock', $quantity);
            }

            $order->update(['status' => OrderStatus::Cancelled]);

            return $order->load('items.product');
        }, attempts: 3);
    }
}
