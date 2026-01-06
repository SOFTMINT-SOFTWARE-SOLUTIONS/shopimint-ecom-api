<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id','product_id','variant_id',
        'title','variant_title','sku',
        'quantity','unit_price','line_total'
    ];
}
