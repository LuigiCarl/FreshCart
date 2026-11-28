<?php

namespace App\Services;

use App\Jobs\SendLowStockAlert;
use App\Models\Product;
use Illuminate\Support\Facades\Log;

class LowStockAlertDispatcher
{
    private const LOW_STOCK_THRESHOLD = 5;

    public function dispatchIfThresholdCrossed(Product $product, int $stockBefore, int $remainingStock): void
    {
        if ($stockBefore < self::LOW_STOCK_THRESHOLD || $remainingStock >= self::LOW_STOCK_THRESHOLD) {
            return;
        }

        $adminEmail = config('services.low_stock_alert_email');

        if (! is_string($adminEmail) || trim($adminEmail) === '') {
            Log::warning('Low-stock alert was not queued because LOW_STOCK_ALERT_EMAIL is not configured.', [
                'product_id' => $product->id,
                'remaining_stock' => $remainingStock,
            ]);

            return;
        }

        SendLowStockAlert::dispatch(
            $product->id,
            $product->name,
            $remainingStock,
            trim($adminEmail),
        )->afterCommit();
    }
}
