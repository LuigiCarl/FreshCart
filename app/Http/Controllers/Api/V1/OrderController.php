<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CancelOrderAction;
use App\Actions\PlaceOrderAction;
use App\Enums\OrderStatus;
use App\Http\Requests\Api\V1\PlaceOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class OrderController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'status' => ['sometimes', Rule::enum(OrderStatus::class)],
            'customer_email' => ['sometimes', 'email', 'max:255'],
        ]);

        $orders = Order::query()
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->when(
                isset($filters['status']),
                fn ($query) => $query->where('status', $filters['status']),
            )
            ->when(
                isset($filters['customer_email']),
                fn ($query) => $query->where('customer_email', $filters['customer_email']),
            )
            ->latest('id')
            ->paginate(15);

        return OrderResource::collection($orders);
    }

    public function store(PlaceOrderRequest $request, PlaceOrderAction $placeOrder): JsonResponse
    {
        $data = $request->validated();
        $idempotencyKey = $data['idempotency_key'] ?? null;
        unset($data['idempotency_key']);

        $order = $placeOrder->execute($data, (int) $request->user()->getAuthIdentifier(), $idempotencyKey);

        return OrderResource::make($order)->response()->setStatusCode(201);
    }

    public function show(Request $request, string $orderNumber): OrderResource
    {
        $order = Order::query()
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->with('items.product')
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        return OrderResource::make($order);
    }

    public function cancel(Request $request, string $orderNumber, CancelOrderAction $cancelOrder): JsonResponse
    {
        $order = $cancelOrder->execute($orderNumber, (int) $request->user()->getAuthIdentifier());

        return OrderResource::make($order)->response();
    }
}
