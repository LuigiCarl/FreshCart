<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\UploadOrderImageAction;
use App\Http\Requests\Api\V1\UploadOrderImageRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderImageController
{
    public function store(
        UploadOrderImageRequest $request,
        Order $order,
        UploadOrderImageAction $uploadOrderImage,
    ): OrderResource {
        $updatedOrder = $uploadOrderImage->execute($order, $request->file('image'));

        return OrderResource::make($updatedOrder);
    }

    public function show(Order $order): StreamedResponse
    {
        $disk = Storage::disk('order_images');

        if ($order->image_path === null || ! $disk->exists($order->image_path)) {
            abort(404);
        }

        return $disk->response($order->image_path, headers: [
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
