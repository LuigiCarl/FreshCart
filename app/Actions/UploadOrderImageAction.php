<?php

namespace App\Actions;

use App\Models\Order;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class UploadOrderImageAction
{
    public function execute(Order $order, UploadedFile $image): Order
    {
        $extension = $image->extension() ?: 'bin';
        $filename = sprintf('%s-%s.%s', $order->order_number, Str::lower((string) Str::ulid()), $extension);
        $disk = Storage::disk('order_images');

        $storedPath = $disk->putFileAs('', $image, $filename);

        if (! is_string($storedPath) || $storedPath === '') {
            throw new RuntimeException("Unable to store image for order {$order->order_number}.");
        }

        $previousImagePath = null;

        try {
            $updatedOrder = DB::transaction(function () use ($order, $storedPath, &$previousImagePath): Order {
                $lockedOrder = Order::query()
                    ->lockForUpdate()
                    ->findOrFail($order->getKey());

                $previousImagePath = $lockedOrder->image_path;

                $lockedOrder->update([
                    'image_path' => $storedPath,
                ]);

                return $lockedOrder->load('items.product');
            }, attempts: 3);
        } catch (Throwable $exception) {
            $disk->delete($storedPath);

            throw $exception;
        }

        if (is_string($previousImagePath) && $previousImagePath !== '' && $previousImagePath !== $storedPath) {
            try {
                $disk->delete($previousImagePath);
            } catch (Throwable $exception) {
                Log::warning('Unable to delete previous order image after replacement.', [
                    'order_number' => $order->order_number,
                    'previous_image_path' => $previousImagePath,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $updatedOrder;
    }
}
