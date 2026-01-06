<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Product extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'product_type','brand_id','category_id',
        'title','slug','short_description','description',
        'status','featured','seo_title','seo_description',
        'featured_image',
        'compare_price',
        'sell_price',
        'in_stock',
    ];

    protected $casts = [
        'compare_price' => 'decimal:2',
        'sell_price' => 'decimal:2',
        'in_stock' => 'boolean',
    ];


    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(Variant::class);
    }

    /**
     * Shopify-like product gallery
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('gallery')->useDisk('public');
    }

}
