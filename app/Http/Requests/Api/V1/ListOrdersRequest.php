<?php

namespace App\Http\Requests\Api\V1;

use App\Data\OrderFilterData;
use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(OrderStatus::class)],
            'customer_email' => ['sometimes', 'email', 'max:255'],
        ];
    }

    public function toDto(): OrderFilterData
    {
        $validated = $this->validated();

        return new OrderFilterData(
            status: isset($validated['status']) ? OrderStatus::from($validated['status']) : null,
            customerEmail: $validated['customer_email'] ?? null,
        );
    }
}
