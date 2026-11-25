<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class ApiErrorResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_not_found_error_is_json_even_when_html_is_requested(): void
    {
        $this->get('/api/v1/not-found', ['Accept' => 'text/html'])
            ->assertNotFound()
            ->assertHeader('content-type', 'application/json')
            ->assertExactJson([
                'message' => 'Not found.',
                'errors' => null,
            ]);
    }

    public function test_api_validation_error_uses_the_standard_json_shape(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->post('/api/v1/orders', [
            'customer_name' => '',
            'customer_email' => 'invalid',
            'items' => [],
        ], ['Accept' => 'text/html'])
            ->assertUnprocessable()
            ->assertHeader('content-type', 'application/json')
            ->assertJsonStructure([
                'message',
                'errors' => ['customer_name', 'customer_email', 'items'],
            ]);
    }

    public function test_api_server_error_does_not_expose_exception_details(): void
    {
        Route::get('/api/v1/test-server-error', function () {
            throw new RuntimeException('Sensitive internal detail.');
        });

        $this->get('/api/v1/test-server-error', ['Accept' => 'text/html'])
            ->assertInternalServerError()
            ->assertHeader('content-type', 'application/json')
            ->assertExactJson([
                'message' => 'Internal server error.',
                'errors' => null,
            ]);
    }
}
