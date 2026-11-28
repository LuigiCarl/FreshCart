<?php

namespace App\Data;

final readonly class PlaceOrderData
{
    /**
     * @param  list<OrderItemData>  $items
     */
    public function __construct(
        public string $customerName,
        public string $customerEmail,
        public array $items,
        public ?string $idempotencyKey = null,
    ) {}

    /**
     * @return array<int, int>
     */
    public function aggregatedQuantities(): array
    {
        $quantities = [];

        foreach ($this->items as $item) {
            $quantities[$item->productId] = ($quantities[$item->productId] ?? 0) + $item->quantity;
        }

        return $quantities;
    }

    public function requestHash(): string
    {
        return hash('sha256', json_encode([
            'customer_name' => $this->customerName,
            'customer_email' => $this->customerEmail,
            'items' => array_map(
                fn (OrderItemData $item): array => [
                    'product_id' => $item->productId,
                    'quantity' => $item->quantity,
                ],
                $this->items,
            ),
        ], JSON_THROW_ON_ERROR));
    }
}
