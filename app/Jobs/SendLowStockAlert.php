<?php

namespace App\Jobs;

use App\Mail\LowStockAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendLowStockAlert implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60];

    public function __construct(
        public readonly int $productId,
        public readonly string $productName,
        public readonly int $remainingStock,
        public readonly string $recipient,
    ) {}

    public function handle(): void
    {
        Mail::to($this->recipient)->send(new LowStockAlert(
            $this->productId,
            $this->productName,
            $this->remainingStock,
        ));
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Failed to send low-stock alert email.', [
            'product_id' => $this->productId,
            'product_name' => $this->productName,
            'remaining_stock' => $this->remainingStock,
            'recipient' => $this->recipient,
            'error' => $exception?->getMessage(),
        ]);
    }
}
