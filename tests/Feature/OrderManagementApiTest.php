<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderManagementApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_orders_can_be_listed_with_status_and_email_filters(): void
    {
        $matchingOrder = $this->createOrder(OrderStatus::Pending, 'buyer@example.com');
        $this->createOrder(OrderStatus::Completed, 'buyer@example.com');
        $this->createOrder(OrderStatus::Pending, 'another@example.com');

        $this->getJson('/api/v1/orders?status=pending&customer_email=buyer@example.com')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.order_number', $matchingOrder->order_number)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_order_listing_validates_status_and_email_filters(): void
    {
        $this->getJson('/api/v1/orders?status=invalid&customer_email=not-an-email')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'customer_email']);
    }

    public function test_order_listing_is_paginated_fifteen_per_page(): void
    {
        for ($index = 0; $index < 16; $index++) {
            $this->createOrder(OrderStatus::Pending, "buyer{$index}@example.com");
        }

        $this->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.total', 16);
    }

    public function test_order_details_include_items_and_product_names(): void
    {
        $product = Product::factory()->create(['name' => 'Red Apples']);
        $order = $this->createOrder(OrderStatus::Pending, 'buyer@example.com');
        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => '2.00',
            'subtotal' => '6.00',
        ]);

        $this->getJson('/api/v1/orders/'.$order->order_number)
            ->assertOk()
            ->assertJsonPath('data.order_number', $order->order_number)
            ->assertJsonPath('data.items.0.product_name', 'Red Apples')
            ->assertJsonPath('data.items.0.quantity', 3);
    }

    public function test_cancelling_a_pending_order_restores_stock_atomically(): void
    {
        $product = Product::factory()->create(['stock' => 4]);
        $order = $this->createOrder(OrderStatus::Pending, 'buyer@example.com');
        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => '2.00',
            'subtotal' => '6.00',
        ]);

        $this->postJson('/api/v1/orders/'.$order->order_number.'/cancel')
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::Cancelled->value);

        $this->postJson('/api/v1/orders/'.$order->order_number.'/cancel')
            ->assertUnprocessable();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 7]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::Cancelled->value,
        ]);
    }

    public function test_non_pending_orders_cannot_be_cancelled_or_restore_stock(): void
    {
        $product = Product::factory()->create(['stock' => 4]);
        $order = $this->createOrder(OrderStatus::Processing, 'buyer@example.com');
        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => '2.00',
            'subtotal' => '6.00',
        ]);

        $this->postJson('/api/v1/orders/'.$order->order_number.'/cancel')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 4]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::Processing->value,
        ]);
    }

    private function createOrder(OrderStatus $status, string $email): Order
    {
        return Order::query()->create([
            'user_id' => $this->user->id,
            'order_number' => 'FC-'.fake()->unique()->numerify('########').'-'.fake()->unique()->bothify('????'),
            'customer_name' => 'Test Customer',
            'customer_email' => $email,
            'status' => $status,
            'total' => '6.00',
        ]);
    }
}
