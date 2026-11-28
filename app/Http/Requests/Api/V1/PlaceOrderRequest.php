<?php

namespace App\Http\Requests\Api\V1;

use App\Data\OrderItemData;
use App\Data\PlaceOrderData;
use Illuminate\Foundation\Http\FormRequest;

class PlaceOrderRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'idempotency_key' => $this->header('Idempotency-Key'),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],
            'idempotency_key' => ['nullable', 'string', 'min:1', 'max:255'],
        ];
    }

    public function toDto(): PlaceOrderData
    {
        $validated = $this->validated();

        return new PlaceOrderData(
            customerName: $validated['customer_name'],
            customerEmail: $validated['customer_email'],
            items: array_values(array_map(
                fn (array $item): OrderItemData => new OrderItemData(
                    productId: (int) $item['product_id'],
                    quantity: (int) $item['quantity'],
                ),
                $validated['items'],
            )),
            idempotencyKey: $validated['idempotency_key'] ?? null,
        );
    }
}
