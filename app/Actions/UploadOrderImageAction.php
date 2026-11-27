<?php

namespace App\Actions;

use App\Models\Order;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class UploadOrderImageAction
{
    public function execute(string $orderNumber, int $userId, UploadedFile $image): Order
    {
        $order = Order::query()
            ->where('user_id', $userId)
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        $extension = $image->extension() ?: 'bin';
        $filename = sprintf('%s-%s.%s', $order->order_number, Str::lower((string) Str::ulid()), $extension);
        $disk = Storage::disk('order_images');

        $storedPath = $disk->putFileAs('', $image, $filename);

        if (! is_string($storedPath) || $storedPath === '') {
            throw new RuntimeException("Unable to store image for order {$order->order_number}.");
        }

        $previousImagePath = null;

        try {
            $updatedOrder = DB::transaction(function () use ($orderNumber, $userId, $storedPath, &$previousImagePath): Order {
                $lockedOrder = Order::query()
                    ->where('user_id', $userId)
                    ->where('order_number', $orderNumber)
                    ->lockForUpdate()
                    ->firstOrFail();

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
            $disk->delete($previousImagePath);
        }

        return $updatedOrder;
    }
}
