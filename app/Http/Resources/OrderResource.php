<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'order_number' => $this->order_number,
            'customer_name' => $this->customer_name,
            'customer_email' => $this->customer_email,
            'status' => $this->status->value,
            'total' => $this->total,
            'invoice_path' => $this->invoice_path,
            'image_path' => $this->image_path,
            'image_url' => $this->image_path !== null
                ? route('api.v1.orders.image.show', ['order_number' => $this->order_number])
                : null,
            'processed_at' => $this->processed_at,
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
