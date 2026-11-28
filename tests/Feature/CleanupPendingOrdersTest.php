<?php

namespace Tests\Feature;

use App\Actions\CancelOrderAction;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CleanupPendingOrdersTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_cancels_only_stale_pending_orders_and_restores_stock_once(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 2]);
        $stalePending = $this->createOrder($user, $product, OrderStatus::Pending, now()->subMinutes(31), 3);
        $recentPending = $this->createOrder($user, $product, OrderStatus::Pending, now()->subMinutes(29), 4);
        $processing = $this->createOrder($user, $product, OrderStatus::Processing, now()->subMinutes(45), 5);

        $this->artisan('orders:cleanup-pending')->assertSuccessful();

        $this->assertDatabaseHas('orders', [
            'id' => $stalePending->id,
            'status' => OrderStatus::Cancelled->value,
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $recentPending->id,
            'status' => OrderStatus::Pending->value,
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $processing->id,
            'status' => OrderStatus::Processing->value,
        ]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 5]);

        $this->artisan('orders:cleanup-pending')->assertSuccessful();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 5]);
    }

    public function test_cleanup_skips_an_order_that_is_no_longer_pending(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 2]);
        $order = $this->createOrder($user, $product, OrderStatus::Processing, now()->subMinutes(45), 3);

        $result = app(CancelOrderAction::class)->cancelIfPending($order);

        $this->assertNull($result);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::Processing->value,
        ]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 2]);
    }

    private function createOrder(
        User $user,
        Product $product,
        OrderStatus $status,
        Carbon $createdAt,
        int $quantity,
    ): Order {
        $subtotal = number_format(2 * $quantity, 2, '.', '');

        $order = Order::query()->create([
            'user_id' => $user->id,
            'order_number' => 'FC-'.fake()->unique()->numerify('########').'-'.fake()->unique()->bothify('????'),
            'customer_name' => 'Test Customer',
            'customer_email' => $user->email,
            'status' => $status,
            'total' => $subtotal,
        ]);
        $order->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => '2.00',
            'subtotal' => $subtotal,
        ]);

        return $order;
    }
}
