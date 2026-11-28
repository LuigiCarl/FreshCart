<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CancelOrderAction;
use App\Actions\PlaceOrderAction;
use App\Http\Requests\Api\V1\ListOrdersRequest;
use App\Http\Requests\Api\V1\PlaceOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController
{
    public function index(ListOrdersRequest $request): AnonymousResourceCollection
    {
        $orders = Order::query()
            ->forUser($request->user())
            ->filter($request->toDto())
            ->latest('id')
            ->paginate(15);

        return OrderResource::collection($orders);
    }

    public function store(PlaceOrderRequest $request, PlaceOrderAction $placeOrder): JsonResponse
    {
        $order = $placeOrder->execute($request->user(), $request->toDto());

        return OrderResource::make($order)->response()->setStatusCode(201);
    }

    public function show(Order $order): OrderResource
    {
        return OrderResource::make($order->loadMissing('items.product'));
    }

    public function cancel(Order $order, CancelOrderAction $cancelOrder): JsonResponse
    {
        $cancelledOrder = $cancelOrder->execute($order);

        return OrderResource::make($cancelledOrder)->response();
    }
}
