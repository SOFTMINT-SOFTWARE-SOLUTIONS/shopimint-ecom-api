<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Variant extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'product_id','title','sku','barcode',
        'price','compare_at_price','cost_price','currency',
        'is_active','is_default',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('variant_image')
            ->singleFile()
            ->useDisk('public');
    }

    public function optionValues()
    {
        return $this->belongsToMany(
            \App\Models\ProductOptionValue::class,
            'variant_option_values',
            'variant_id',
            'option_value_id'
        );
    }

    protected static function booted(): void
    {
        static::created(function (Variant $variant) {
            \App\Models\InventoryItem::firstOrCreate(['variant_id' => $variant->id]);
        });
    }


}
