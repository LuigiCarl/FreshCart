<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\OrderInvoiceGenerator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrderInvoiceGeneratorTest extends TestCase
{
    public function test_it_formats_and_writes_order_invoice_to_invoices_disk(): void
    {
        Storage::fake('invoices');

        $product = new Product(['name' => 'Organic Bananas']);
        $item = new OrderItem([
            'quantity' => 2,
            'unit_price' => '3.50',
            'subtotal' => '7.00',
        ]);
        $item->setRelation('product', $product);

        $order = new Order([
            'order_number' => 'FC-20261128-TEST01',
            'customer_name' => 'Sam Shopper',
            'total' => '7.00',
        ]);
        $order->setRelation('items', new Collection([$item]));

        $generator = new OrderInvoiceGenerator;
        $invoicePath = $generator->generate($order);

        $this->assertSame('invoices/FC-20261128-TEST01.txt', $invoicePath);
        Storage::disk('invoices')->assertExists('FC-20261128-TEST01.txt');

        $contents = Storage::disk('invoices')->get('FC-20261128-TEST01.txt');
        $this->assertStringContainsString('FreshCart Invoice', $contents);
        $this->assertStringContainsString('Order: FC-20261128-TEST01', $contents);
        $this->assertStringContainsString('Customer: Sam Shopper', $contents);
        $this->assertStringContainsString('Organic Bananas x2 @ 3.50 = 7.00', $contents);
        $this->assertStringContainsString('Total: 7.00', $contents);
    }
}
