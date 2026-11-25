<?php

namespace App\Console\Commands;

use App\Actions\CancelOrderAction;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Console\Command;

class CleanupPendingOrders extends Command
{
    protected $signature = 'orders:cleanup-pending';

    protected $description = 'Cancel pending orders older than 30 minutes and restore stock';

    public function handle(CancelOrderAction $cancelOrder): int
    {
        $cutoff = now()->subMinutes(30);
        $cancelledCount = 0;

        Order::query()
            ->where('status', OrderStatus::Pending->value)
            ->where('created_at', '<=', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function ($orders) use ($cancelOrder, &$cancelledCount): void {
                foreach ($orders as $order) {
                    if ($cancelOrder->cancelIfPending($order->order_number) !== null) {
                        $cancelledCount++;
                    }
                }
            });

        $this->info("Cancelled {$cancelledCount} stale pending order(s).");

        return self::SUCCESS;
    }
}
