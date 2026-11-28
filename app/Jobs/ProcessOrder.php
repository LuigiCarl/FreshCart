<?php

namespace App\Jobs;

use App\Enums\OrderStatus;
use App\Mail\SendOrderConfirmation;
use App\Models\Order;
use App\Services\OrderInvoiceGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ProcessOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60];

    public function __construct(public readonly int $orderId) {}

    public function handle(?OrderInvoiceGenerator $invoiceGenerator = null): void
    {
        $invoiceGenerator ??= app(OrderInvoiceGenerator::class);

        $order = DB::transaction(function (): ?Order {
            $order = Order::query()
                ->with('items.product')
                ->lockForUpdate()
                ->findOrFail($this->orderId);

            if ($order->status->isTerminal()) {
                return null;
            }

            $order->update(['status' => OrderStatus::Processing]);

            return $order;
        }, attempts: 3);

        if ($order === null) {
            return;
        }

        $invoicePath = $invoiceGenerator->generate($order);

        DB::transaction(function () use ($invoicePath): void {
            $order = Order::query()
                ->with('items.product')
                ->lockForUpdate()
                ->findOrFail($this->orderId);

            if ($order->status->isTerminal()) {
                return;
            }

            $order->update([
                'status' => OrderStatus::Completed,
                'invoice_path' => $invoicePath,
                'processed_at' => now(),
                'failure_reason' => null,
            ]);

            DB::afterCommit(function () use ($order): void {
                Mail::to($order->customer_email)->queue(new SendOrderConfirmation($order));
            });
        }, attempts: 3);
    }

    public function failed(?Throwable $exception): void
    {
        Order::query()
            ->whereKey($this->orderId)
            ->whereNotIn('status', [OrderStatus::Completed->value, OrderStatus::Cancelled->value])
            ->update([
                'status' => OrderStatus::Failed->value,
                'failure_reason' => $exception?->getMessage() ?? 'Order processing failed.',
            ]);
    }
}
