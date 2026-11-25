<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customers_can_register_and_receive_a_bearer_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Taylor Customer',
            'email' => 'taylor@example.com',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ]);

        $response->assertCreated()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.email', 'taylor@example.com')
            ->assertJsonMissingPath('user.password');

        $this->assertDatabaseHas('users', ['email' => 'taylor@example.com']);
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->withToken($response->json('token'))
            ->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_customers_can_log_in_and_revoke_their_current_token(): void
    {
        $user = User::factory()->create([
            'email' => 'taylor@example.com',
            'password' => 'StrongPassword123!',
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'taylor@example.com',
            'password' => 'StrongPassword123!',
        ]);

        $login->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.id', $user->id);

        $token = $login->json('token');

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logged out.');

        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/v1/orders')
            ->assertUnauthorized()
            ->assertJsonPath('errors', null);
    }

    public function test_product_listing_is_public_while_order_endpoints_require_a_token(): void
    {
        $this->getJson('/api/v1/products')->assertOk();
        $this->get('/api/v1/orders', ['Accept' => 'text/html'])
            ->assertUnauthorized()
            ->assertHeader('content-type', 'application/json')
            ->assertExactJson([
                'message' => 'Unauthenticated.',
                'errors' => null,
            ]);
        $this->postJson('/api/v1/orders', [])->assertUnauthorized();
        $this->getJson('/api/v1/orders/unknown')->assertUnauthorized();
        $this->postJson('/api/v1/orders/unknown/cancel')->assertUnauthorized();
    }

    public function test_customers_can_only_list_view_and_cancel_their_own_orders(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $product = Product::factory()->create(['stock' => 4]);
        $ownedOrder = $this->createOrder($owner, $product);
        $this->createOrder($otherUser, $product);

        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.order_number', $ownedOrder->order_number);

        Sanctum::actingAs($otherUser);

        $this->getJson('/api/v1/orders/'.$ownedOrder->order_number)->assertNotFound();
        $this->postJson('/api/v1/orders/'.$ownedOrder->order_number.'/cancel')->assertNotFound();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 4]);
        $this->assertDatabaseHas('orders', [
            'id' => $ownedOrder->id,
            'status' => OrderStatus::Pending->value,
        ]);
    }

    public function test_invalid_login_returns_unauthorized_without_exposing_account_state(): void
    {
        User::factory()->create(['email' => 'taylor@example.com']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'taylor@example.com',
            'password' => 'incorrect-password',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'The provided credentials are incorrect.');
    }

    private function createOrder(User $user, Product $product): Order
    {
        return Order::query()->create([
            'user_id' => $user->id,
            'order_number' => 'FC-'.fake()->unique()->numerify('########').'-'.fake()->unique()->bothify('????'),
            'customer_name' => 'Test Customer',
            'customer_email' => $user->email,
            'status' => OrderStatus::Pending,
            'total' => '2.00',
        ]);
    }
}
