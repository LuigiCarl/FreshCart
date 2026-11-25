<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Jobs\ProcessOrder;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrderCheckoutApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_are_paginated_fifteen_per_page(): void
    {
        Product::factory()->count(16)->create();

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.total', 16);
    }

    public function test_order_uses_database_prices_and_dispatches_processing_after_commit(): void
    {
        Queue::fake();
        $product = Product::factory()->create([
            'name' => 'Apples',
            'price' => '4.25',
            'stock' => 8,
        ]);

        $response = $this->postJson('/api/v1/orders', [
            'customer_name' => 'Taylor Customer',
            'customer_email' => 'taylor@example.com',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => '0.01'],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', OrderStatus::Pending->value)
            ->assertJsonPath('data.total', '8.50')
            ->assertJsonPath('data.items.0.unit_price', '4.25')
            ->assertJsonPath('data.items.0.subtotal', '8.50');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 6]);
        $this->assertDatabaseHas('orders', [
            'customer_email' => 'taylor@example.com',
            'total' => '8.50',
            'status' => OrderStatus::Pending->value,
        ]);
        Queue::assertPushed(ProcessOrder::class, 1);
    }

    public function test_insufficient_stock_returns_named_product_and_rolls_back_order(): void
    {
        Queue::fake();
        $product = Product::factory()->create([
            'name' => 'Fresh Mangoes',
            'stock' => 1,
        ]);

        $this->postJson('/api/v1/orders', [
            'customer_name' => 'Taylor Customer',
            'customer_email' => 'taylor@example.com',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.items.0', 'Insufficient stock for Fresh Mangoes. Available: 1; requested: 2.');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 1]);
        Queue::assertNothingPushed();
    }

    public function test_invalid_order_request_returns_validation_errors(): void
    {
        $this->postJson('/api/v1/orders', [
            'customer_name' => '',
            'customer_email' => 'not-an-email',
            'items' => [],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_name', 'customer_email', 'items']);
    }

    public function test_processing_job_is_idempotent_and_queues_confirmation_email(): void
    {
        $product = Product::factory()->create(['name' => 'Pears']);
        $order = Order::query()->create([
            'order_number' => 'FC-20261125-TEST-ORDER',
            'customer_name' => 'Taylor Customer',
            'customer_email' => 'taylor@example.com',
            'status' => OrderStatus::Pending,
            'total' => '3.50',
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => '3.50',
            'subtotal' => '3.50',
        ]);

        \Illuminate\Support\Facades\Storage::fake('invoices');
        \Illuminate\Support\Facades\Mail::fake();

        $job = new ProcessOrder($order->id);
        $job->handle();
        $job->handle();

        $order->refresh();
        $this->assertSame(OrderStatus::Completed, $order->status);
        $this->assertSame('invoices/'.$order->order_number.'.txt', $order->invoice_path);
        $this->assertNotNull($order->processed_at);
        $this->assertTrue(\Illuminate\Support\Facades\Storage::disk('invoices')->exists($order->order_number.'.txt'));
        \Illuminate\Support\Facades\Mail::assertQueued(\App\Mail\SendOrderConfirmation::class, 1);
    }

    public function test_failed_processing_job_records_failure_reason(): void
    {
        $order = Order::query()->create([
            'order_number' => 'FC-20261125-FAILED-ORDER',
            'customer_name' => 'Taylor Customer',
            'customer_email' => 'taylor@example.com',
            'status' => OrderStatus::Pending,
            'total' => '0.00',
        ]);

        (new ProcessOrder($order->id))->failed(new \RuntimeException('Invoice write failed.'));

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::Failed->value,
            'failure_reason' => 'Invoice write failed.',
        ]);
    }
}
