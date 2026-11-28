<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class OrderInvoiceGenerator
{
    public function generate(Order $order): string
    {
        $filename = $order->order_number.'.txt';
        $invoicePath = 'invoices/'.$filename;
        $written = Storage::disk('invoices')->put($filename, $this->format($order));

        if (! $written) {
            throw new RuntimeException("Unable to write invoice for order {$order->order_number}.");
        }

        return $invoicePath;
    }

    public function format(Order $order): string
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
