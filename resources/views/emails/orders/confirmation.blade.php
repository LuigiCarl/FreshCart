<h1>Thanks for your order, {{ $order->customer_name }}.</h1>
<p>Your FreshCart order {{ $order->order_number }} is confirmed.</p>
<ul>
    @foreach ($order->items as $item)
        <li>{{ $item->product->name }} &times; {{ $item->quantity }} — {{ $item->subtotal }}</li>
    @endforeach
</ul>
<p>Total: {{ $order->total }}</p>
