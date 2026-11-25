<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Jobs\ProcessOrder;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class PlaceOrderAction
{
    /**
     * @param  array{customer_name: string, customer_email: string, items: array<int, array{product_id: int, quantity: int}>}  $data
     */
    public function execute(array $data): Order
    {
        $order = DB::transaction(function () use ($data): Order {
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
                'order_number' => sprintf('FC-%s-%s', now()->format('Ymd'), Str::upper((string) Str::ulid())),
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'],
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
                $products->get($productId)->decrement('stock', $quantity);
            }

            $order->update(['total' => $this->fromCents($totalCents)]);

            ProcessOrder::dispatch($order->getKey())->afterCommit();

            return $order;
        }, attempts: 3);

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
