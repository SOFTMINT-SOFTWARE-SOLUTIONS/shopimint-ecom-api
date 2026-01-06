@component('mail::message')
# Thanks! Your order is placed 🎉

**Order Number:** {{ $order->order_number }}  
**Status: {{ \App\Models\Order::statusOptions()[$order->status] ?? $order->status }}
**Payment:** {{ $order->payment_status }}

@if($order->fulfillment_method === 'delivery')
**Fulfillment:** Delivery  
@else
**Fulfillment:** Pickup from shop  
@endif

---

## Items
@component('mail::table')
| Item | SKU | Qty | Price |
|:--|:--|--:|--:|
@foreach($order->items as $item)
| {{ $item->title }} ({{ $item->variant_title }}) | {{ $item->sku }} | {{ $item->quantity }} | {{ number_format((float)$item->line_total, 2) }} |
@endforeach
@endcomponent

**Grand Total ({{ $order->currency ?? 'LKR' }}):** {{ number_format((float)$order->grand_total, 2) }}

@if(!empty($order->notes))
**Notes:** {{ $order->notes }}
@endif

---

If you have any questions, reply to this email.

Thanks,  
**Siriwardana Mobile**
@endcomponent
