<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentIntent extends Model
{
    protected $fillable = [
        'order_id',
        'payment_method_id',
        'gateway_id',
        'amount',
        'currency',
        'status',
        'gateway_reference',
        'redirect_url',
        'request_payload',
        'response_payload',
        'webhook_payload',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'webhook_payload'  => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function gateway()
    {
        return $this->belongsTo(PaymentGateway::class);
    }

    public function method()
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

}
