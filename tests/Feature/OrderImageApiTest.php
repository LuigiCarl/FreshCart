<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderImageApiTest extends TestCase
{
    use RefreshDatabase;

    private const TINY_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    public function test_existing_orders_without_image_remain_valid_and_return_null_image_fields(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $order = $this->createOrder($user);

        $this->assertNull($order->fresh()->image_path);

        $this->getJson('/api/v1/orders/'.$order->order_number)
            ->assertOk()
            ->assertJsonPath('data.order_number', $order->order_number)
            ->assertJsonPath('data.image_path', null)
            ->assertJsonPath('data.image_url', null);

        $this->get('/api/v1/orders/'.$order->order_number.'/image')
            ->assertNotFound();
    }

    public function test_owner_can_upload_and_view_order_image(): void
    {
        Storage::fake('order_images');

        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $order = $this->createOrder($user);

        $response = $this->post('/api/v1/orders/'.$order->order_number.'/image', [
            'image' => $this->fakePng('receipt.png'),
        ], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonPath('data.order_number', $order->order_number)
            ->assertJsonPath(
                'data.image_url',
                route('api.v1.orders.image.show', ['order_number' => $order->order_number]),
            );

        $imagePath = $response->json('data.image_path');

        $this->assertIsString($imagePath);
        $this->assertStringStartsWith($order->order_number.'-', $imagePath);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'image_path' => $imagePath,
        ]);
        Storage::disk('order_images')->assertExists($imagePath);

        $this->get('/api/v1/orders/'.$order->order_number.'/image')
            ->assertOk()
            ->assertHeader('content-type', 'image/png');
    }

    public function test_replacing_order_image_deletes_the_previous_file(): void
    {
        Storage::fake('order_images');

        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $order = $this->createOrder($user);

        $firstPath = $this->post('/api/v1/orders/'.$order->order_number.'/image', [
            'image' => $this->fakePng('first.png'),
        ], ['Accept' => 'application/json'])->assertOk()->json('data.image_path');

        $secondPath = $this->post('/api/v1/orders/'.$order->order_number.'/image', [
            'image' => $this->fakePng('second.png'),
        ], ['Accept' => 'application/json'])->assertOk()->json('data.image_path');

        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('order_images')->assertMissing($firstPath);
        Storage::disk('order_images')->assertExists($secondPath);
        $this->assertSame($secondPath, $order->fresh()->image_path);
    }

    public function test_image_upload_validates_file_type_and_size(): void
    {
        Storage::fake('order_images');

        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $order = $this->createOrder($user);

        $this->post('/api/v1/orders/'.$order->order_number.'/image', [
            'image' => UploadedFile::fake()->createWithContent('notes.txt', 'not an image'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['image']);

        $oversizedPng = base64_decode(self::TINY_PNG_BASE64, true).str_repeat("\0", 2049 * 1024);

        $this->post('/api/v1/orders/'.$order->order_number.'/image', [
            'image' => UploadedFile::fake()->createWithContent('large.png', $oversizedPng),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['image']);

        $this->assertNull($order->fresh()->image_path);
        $this->assertSame([], Storage::disk('order_images')->allFiles());
    }

    public function test_order_image_endpoints_enforce_authentication_and_ownership(): void
    {
        Storage::fake('order_images');

        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $order = $this->createOrder($owner);

        $this->postJson('/api/v1/orders/'.$order->order_number.'/image', [])->assertUnauthorized();
        $this->getJson('/api/v1/orders/'.$order->order_number.'/image')->assertUnauthorized();

        Sanctum::actingAs($owner);
        $imagePath = $this->post('/api/v1/orders/'.$order->order_number.'/image', [
            'image' => $this->fakePng('receipt.png'),
        ], ['Accept' => 'application/json'])->assertOk()->json('data.image_path');

        Sanctum::actingAs($otherUser);

        $this->get('/api/v1/orders/'.$order->order_number.'/image', ['Accept' => 'application/json'])
            ->assertNotFound();

        $this->post('/api/v1/orders/'.$order->order_number.'/image', [
            'image' => $this->fakePng('hijack.png'),
        ], ['Accept' => 'application/json'])
            ->assertNotFound();

        $this->assertSame($imagePath, $order->fresh()->image_path);
        $this->assertCount(1, Storage::disk('order_images')->allFiles());
    }

    private function createOrder(User $user): Order
    {
        return Order::query()->create([
            'user_id' => $user->id,
            'order_number' => 'FC-'.fake()->unique()->numerify('########').'-'.fake()->unique()->bothify('????'),
            'customer_name' => 'Test Customer',
            'customer_email' => $user->email,
            'status' => OrderStatus::Pending,
            'total' => '12.50',
        ]);
    }

    private function fakePng(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode(self::TINY_PNG_BASE64, true),
        );
    }
}
