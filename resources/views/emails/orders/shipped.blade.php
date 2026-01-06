@component('mail::message')
# Your order is shipped 🚚

**Order Number:** {{ $order->order_number }}

@if($order->courier_company)
**Courier:** {{ $order->courier_company }}
@endif

@if($order->tracking_number)
**Tracking Number:** {{ $order->tracking_number }}
@endif

@if($order->shipped_at)
**Shipped At:** {{ $order->shipped_at }}
@endif

---

## Items
@component('mail::table')
| Item | SKU | Qty |
|:--|:--|--:|
@foreach($order->items as $item)
| {{ $item->title }} ({{ $item->variant_title }}) | {{ $item->sku }} | {{ $item->quantity }} |
@endforeach
@endcomponent

Thanks,  
**Siriwardana Mobile**
@endcomponent
