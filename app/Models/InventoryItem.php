<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    protected $fillable = ['variant_id'];

    public function variant()
    {
        return $this->belongsTo(Variant::class);
    }

    public function levels()
    {
        return $this->hasMany(InventoryLevel::class);
    }
}
