<?php

namespace App\Jobs;

use App\Enums\OrderStatus;
use App\Mail\SendOrderConfirmation;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
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

    public function handle(): void
    {
        $order = DB::transaction(function (): ?Order {
            $order = Order::query()
                ->with('items.product')
                ->lockForUpdate()
                ->findOrFail($this->orderId);

            if (in_array($order->status, [OrderStatus::Completed, OrderStatus::Cancelled], true)) {
                return null;
            }

            $order->update(['status' => OrderStatus::Processing]);

            return $order;
        }, attempts: 3);

        if ($order === null) {
            return;
        }

        $filename = $order->order_number.'.txt';
        $invoicePath = 'invoices/'.$filename;
        $written = Storage::disk('invoices')->put($filename, $this->invoiceContents($order));

        if (! $written) {
            throw new RuntimeException("Unable to write invoice for order {$order->order_number}.");
        }

        DB::transaction(function () use ($invoicePath): void {
            $order = Order::query()
                ->with('items.product')
                ->lockForUpdate()
                ->findOrFail($this->orderId);

            if (in_array($order->status, [OrderStatus::Completed, OrderStatus::Cancelled], true)) {
                return;
            }

            $order->update([
                'status' => OrderStatus::Completed,
                'invoice_path' => $invoicePath,
                'processed_at' => now(),
                'failure_reason' => null,
            ]);

            Mail::to($order->customer_email)->queue(new SendOrderConfirmation($order));
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

    private function invoiceContents(Order $order): string
    {
        $lines = [
            'FreshCart Invoice',
            'Order: '.$order->order_number,
            'Customer: '.$order->customer_name,
            '',
        ];

        foreach ($order->items as $item) {
            $lines[] = sprintf(
                '%s x%d @ %s = %s',
                $item->product->name,
                $item->quantity,
                $item->unit_price,
                $item->subtotal,
            );
        }

        $lines[] = '';
        $lines[] = 'Total: '.$order->total;

        return implode(PHP_EOL, $lines);
    }
}
