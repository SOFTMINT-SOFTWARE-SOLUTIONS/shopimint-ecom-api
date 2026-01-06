<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryReservation extends Model
{
    protected $fillable = [
        'order_id',
        'variant_id',
        'location_id',
        'quantity',
        'status',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function variant()
    {
        return $this->belongsTo(Variant::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }
}
