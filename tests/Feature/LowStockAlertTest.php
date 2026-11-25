<?php

namespace Tests\Feature;

use App\Jobs\SendLowStockAlert;
use App\Mail\LowStockAlert;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LowStockAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    public function test_alert_is_queued_only_when_checkout_crosses_below_five(): void
    {
        Queue::fake();
        config(['services.low_stock_alert_email' => 'admin@example.com']);
        $product = Product::factory()->create(['stock' => 5]);

        $this->placeOrder($product)->assertCreated();
        $this->placeOrder($product)->assertCreated();

        Queue::assertPushed(SendLowStockAlert::class, 1);
        Queue::assertPushed(SendLowStockAlert::class, fn (SendLowStockAlert $job): bool => $job->productId === $product->id
            && $job->productName === $product->name
            && $job->remainingStock === 4
            && $job->recipient === 'admin@example.com');
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 3]);
    }

    public function test_no_alert_is_queued_without_crossing_the_threshold(): void
    {
        Queue::fake();
        config(['services.low_stock_alert_email' => 'admin@example.com']);
        $alreadyLow = Product::factory()->create(['stock' => 4]);
        $stillAbove = Product::factory()->create(['stock' => 8]);

        $this->postJson('/api/v1/orders', [
            'customer_name' => 'Taylor Customer',
            'customer_email' => 'taylor@example.com',
            'items' => [
                ['product_id' => $alreadyLow->id, 'quantity' => 1],
                ['product_id' => $stillAbove->id, 'quantity' => 2],
            ],
        ])->assertCreated();

        Queue::assertNotPushed(SendLowStockAlert::class);
        $this->assertDatabaseHas('products', ['id' => $alreadyLow->id, 'stock' => 3]);
        $this->assertDatabaseHas('products', ['id' => $stillAbove->id, 'stock' => 6]);
    }

    public function test_checkout_continues_and_logs_when_no_alert_email_is_configured(): void
    {
        Queue::fake();
        Log::spy();
        config(['services.low_stock_alert_email' => null]);
        $product = Product::factory()->create(['stock' => 5]);

        $this->placeOrder($product)->assertCreated();

        Queue::assertNotPushed(SendLowStockAlert::class);
        Log::shouldHaveReceived('warning')->once();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 4]);
    }

    public function test_alert_job_sends_a_low_stock_email(): void
    {
        Mail::fake();

        (new SendLowStockAlert(42, 'Red Apples', 4, 'admin@example.com'))->handle();

        Mail::assertSent(LowStockAlert::class, fn (LowStockAlert $mail): bool => $mail->productId === 42
            && $mail->productName === 'Red Apples'
            && $mail->remainingStock === 4);
    }

    private function placeOrder(Product $product): TestResponse
    {
        return $this->postJson('/api/v1/orders', [
            'customer_name' => 'Taylor Customer',
            'customer_email' => 'taylor@example.com',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);
    }
}
