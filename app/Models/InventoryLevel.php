<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryLevel extends Model
{
    protected $fillable = ['inventory_item_id','location_id','available','reserved'];

    public function item()
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }
}
