<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $order->order_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        .row { width: 100%; }
        .col { display: inline-block; vertical-align: top; }
        .right { text-align: right; }
        .muted { color: #666; }
        h1 { font-size: 18px; margin: 0 0 6px 0; }
        h2 { font-size: 14px; margin: 16px 0 8px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px; border-bottom: 1px solid #e5e7eb; }
        th { background: #f5f5f5; text-align: left; }
        .totals td { border: none; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 10px; background: #f3f4f6; font-size: 11px; }
        .box { border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px; }
        .small { font-size: 11px; }
    </style>
</head>
<body>

<div class="row">
    <div class="col" style="width: 60%;">
        <h1>Siriwardana Mobile</h1>
        <div class="muted small">
            Mobile Phones • Accessories • Smart Watches<br>
            {{-- Add your shop address/contact here --}}
            {{-- No: ... Colombo, Sri Lanka | +94... | admin@siriwardanamobile.lk --}}
        </div>
    </div>

    <div class="col right" style="width: 39%;">
        <div class="box">
            <div><strong>Invoice</strong></div>
            <div class="muted">Order: {{ $order->order_number }}</div>
            <div class="muted">Date: {{ optional($order->created_at)->format('Y-m-d H:i') }}</div>
            <div class="muted">Status: <span class="badge">{{ $order->status }}</span></div>
            <div class="muted">Payment: <span class="badge">{{ $order->payment_status }}</span></div>
        </div>
    </div>

    @if($order->courier_company || $order->tracking_number)
        <div class="muted small" style="margin-top:6px;">
            Courier: <strong>{{ $order->courier_company }}</strong><br>
            Tracking #: <strong>{{ $order->tracking_number }}</strong>
        </div>
    @endif
</div>

<h2>Bill To</h2>
<div class="box">
    <strong>{{ $order->guest_name ?? ($order->customer->first_name ?? 'Customer') }}</strong><br>
    <span class="muted">{{ $order->guest_phone }}</span><br>
    <span class="muted">{{ $order->guest_email }}</span>
</div>

@if($order->fulfillment_method === 'delivery')
    <h2>Ship To</h2>
    <div class="box small">
        @php $addr = is_array($order->shipping_address_json) ? $order->shipping_address_json : []; @endphp
        {{ $addr['name'] ?? $order->guest_name ?? 'Customer' }}<br>
        {{ $addr['address'] ?? $addr['line1'] ?? '' }} {{ $addr['line2'] ?? '' }}<br>
        {{ $addr['city'] ?? '' }} {{ $addr['postal_code'] ?? '' }}<br>
        {{ $addr['country'] ?? 'Sri Lanka' }}
    </div>
@else
    <h2>Pickup</h2>
    <div class="box small">
        Pickup from shop (Customer will collect).<br>
        Order: <strong>{{ $order->order_number }}</strong>
    </div>
@endif

<h2>Items</h2>
<table>
    <thead>
    <tr>
        <th>Item</th>
        <th>SKU</th>
        <th class="right">Unit</th>
        <th class="right">Qty</th>
        <th class="right">Total</th>
    </tr>
    </thead>
    <tbody>
    @foreach($order->items as $item)
        <tr>
            <td>
                <strong>{{ $item->title }}</strong><br>
                <span class="muted small">{{ $item->variant_title }}</span>
            </td>
            <td>{{ $item->sku }}</td>
            <td class="right">{{ number_format((float)$item->unit_price, 2) }}</td>
            <td class="right">{{ (int)$item->quantity }}</td>
            <td class="right">{{ number_format((float)$item->line_total, 2) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<table class="totals" style="margin-top: 12px;">
    <tr>
        <td class="right" style="width: 80%;"><strong>Subtotal</strong></td>
        <td class="right" style="width: 20%;">{{ number_format((float)$order->subtotal, 2) }}</td>
    </tr>
    <tr>
        <td class="right"><strong>Discount</strong></td>
        <td class="right">- {{ number_format((float)$order->discount_total, 2) }}</td>
    </tr>
    <tr>
        <td class="right"><strong>Shipping</strong></td>
        <td class="right">{{ number_format((float)$order->shipping_total, 2) }}</td>
    </tr>
    <tr>
        <td class="right"><strong>Tax</strong></td>
        <td class="right">{{ number_format((float)$order->tax_total, 2) }}</td>
    </tr>
    <tr>
        <td class="right" style="font-size: 14px;"><strong>Grand Total ({{ $order->currency ?? 'LKR' }})</strong></td>
        <td class="right" style="font-size: 14px;"><strong>{{ number_format((float)$order->grand_total, 2) }}</strong></td>
    </tr>
</table>

<div style="margin-top: 16px;" class="small muted">
    Thank you for shopping with Siriwardana Mobile.
</div>

</body>
</html>
