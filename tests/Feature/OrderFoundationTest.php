<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_at_least_ten_products(): void
    {
        $this->seed();

        $this->assertGreaterThanOrEqual(10, Product::query()->count());
    }

    public function test_order_items_belong_to_order_and_product_and_order_status_is_cast(): void
    {
        $product = Product::factory()->create();
        $order = Order::create([
            'order_number' => 'FC-20261125-0001',
            'customer_name' => 'Test Customer',
            'customer_email' => 'customer@example.com',
            'status' => OrderStatus::Pending,
            'total' => '12.50',
        ]);
        $item = $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => '6.25',
            'subtotal' => '12.50',
        ]);

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertTrue($item->order->is($order));
        $this->assertTrue($item->product->is($product));
        $this->assertTrue($product->orderItems->contains($item));
    }
}
