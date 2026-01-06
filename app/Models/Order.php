<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'order_number','customer_id',
        'guest_name','guest_email','guest_phone',
        'currency','subtotal','discount_total','shipping_total','tax_total','grand_total',
        'fulfillment_method','status','payment_status',
        'shipping_address_json','billing_address_json','notes',
        'courier_company',
        'tracking_number',
        'shipped_at',
    ];


    protected $casts = [
        'shipping_address_json' => 'array',
        'billing_address_json' => 'array',
    ];

    

    public function items()
    {
        return $this->hasMany(\App\Models\OrderItem::class);
    }

    public function paymentIntents()
    {
        return $this->hasMany(\App\Models\PaymentIntent::class);
    }

    public function inventoryReservations()
    {
        return $this->hasMany(\App\Models\InventoryReservation::class);
    }

    public function customer()
    {
        return $this->belongsTo(\App\Models\Customer::class);
    }

    public function markShipped(): void
    {
        $this->shipped_at = now();
        $this->status = 'processing';
        $this->save();
    }

    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_READY_TO_PICKUP = 'ready_to_pickup';
    public const STATUS_ON_DELIVERY = 'on_delivery';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_REFUNDED = 'refunded';

    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_CONFIRMED => 'Confirmed',
            self::STATUS_READY_TO_PICKUP => 'Ready to Pickup',
            self::STATUS_ON_DELIVERY => 'On Delivery',
            self::STATUS_DELIVERED => 'Delivered',
            self::STATUS_CANCELED => 'Canceled',
            self::STATUS_REFUNDED => 'Refunded',
        ];
    }
 
}
