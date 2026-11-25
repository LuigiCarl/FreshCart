<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\PlaceOrderAction;
use App\Http\Requests\Api\V1\PlaceOrderRequest;
use App\Http\Resources\OrderResource;
use Illuminate\Http\JsonResponse;

class OrderController
{
    public function store(PlaceOrderRequest $request, PlaceOrderAction $placeOrder): JsonResponse
    {
        $order = $placeOrder->execute($request->validated());

        return OrderResource::make($order)->response()->setStatusCode(201);
    }
}
