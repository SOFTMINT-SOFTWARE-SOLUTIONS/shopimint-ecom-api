<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Label {{ $order->order_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; margin: 10px; }
        .box { border: 1px solid #111; border-radius: 8px; padding: 10px; }
        .row { display: flex; justify-content: space-between; align-items: flex-start; }
        .title { font-size: 14px; font-weight: bold; }
        .big { font-size: 18px; font-weight: bold; letter-spacing: 1px; }
        .muted { color: #666; font-size: 11px; }
        .hr { border-top: 1px dashed #999; margin: 10px 0; }
    </style>
</head>
<body>

<div class="box">
    <div class="row">
        <div>
            <div class="title">Siriwardana Mobile</div>
            <div class="muted">Shipping Label</div>
        </div>
        <div style="text-align:right;">
            <div class="muted">Order</div>
            <div class="big">{{ $order->order_number }}</div>
        </div>

        @if($order->courier_company || $order->tracking_number)
            <div class="hr"></div>
            <div class="muted">COURIER</div>
            <div style="font-size: 14px; font-weight: bold;">
                {{ $order->courier_company ?? 'Courier' }}
            </div>
            <div class="muted">Tracking Number</div>
            <div class="big">
                {{ $order->tracking_number ?? '—' }}
            </div>
        @endif
    </div>

    <div class="hr"></div>

    @if($order->fulfillment_method === 'delivery')
        @php $addr = is_array($order->shipping_address_json) ? $order->shipping_address_json : []; @endphp

        <div class="muted">TO</div>
        <div style="font-size: 14px; font-weight: bold;">
            {{ $addr['name'] ?? $order->guest_name ?? 'Customer' }}
        </div>
        <div style="margin-top: 4px;">
            {{ $addr['address'] ?? $addr['line1'] ?? '' }} {{ $addr['line2'] ?? '' }}<br>
            {{ $addr['city'] ?? '' }} {{ $addr['postal_code'] ?? '' }}<br>
            {{ $addr['country'] ?? 'Sri Lanka' }}<br>
            <strong>{{ $order->guest_phone }}</strong>
        </div>

        <div class="hr"></div>

        <div class="muted">FROM</div>
        <div>
            Siriwardana Mobile (Shop)<br>
            {{-- Put your shop address here --}}
            Colombo, Sri Lanka
        </div>
    @else
        <div class="muted">PICKUP</div>
        <div style="font-size: 14px; font-weight: bold;">
            {{ $order->guest_name ?? 'Customer' }}
        </div>
        <div style="margin-top: 4px;">
            Pickup from shop<br>
            <strong>{{ $order->guest_phone }}</strong>
        </div>
    @endif

</div>

</body>
</html>
