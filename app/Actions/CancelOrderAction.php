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
    public function execute(string $orderNumber): Order
    {
        return DB::transaction(function () use ($orderNumber): Order {
            $order = Order::query()
                ->where('order_number', $orderNumber)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->status !== OrderStatus::Pending) {
                throw ValidationException::withMessages([
                    'status' => 'Only pending orders can be cancelled.',
                ]);
            }

            $items = $order->items()->orderBy('product_id')->orderBy('id')->get();
            $quantities = [];

            foreach ($items as $item) {
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
                    throw new RuntimeException("Product {$productId} for order {$orderNumber} no longer exists.");
                }

                $product->increment('stock', $quantity);
            }

            $order->update(['status' => OrderStatus::Cancelled]);

            return $order->load('items.product');
        }, attempts: 3);
    }
}
