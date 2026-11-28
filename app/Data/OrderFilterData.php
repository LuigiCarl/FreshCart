<?php

namespace App\Data;

use App\Enums\OrderStatus;

final readonly class OrderFilterData
{
    public function __construct(
        public ?OrderStatus $status = null,
        public ?string $customerEmail = null,
    ) {}
}
