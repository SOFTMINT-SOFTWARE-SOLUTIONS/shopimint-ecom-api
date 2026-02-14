<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'parent_id',
        'level',
        'name',
        'slug',
        'description',
        'image_url',
        'is_active',
        'sort_order',
    ];

    protected static function booted(): void
    {
        static::saving(function (Category $category) {
            // If no parent => Level 1
            if (empty($category->parent_id)) {
                $category->level = 1;
                return;
            }

            // Parent exists => level = parent.level + 1
            $parent = Category::query()->find($category->parent_id);

            if (!$parent) {
                // Parent not found => fallback to level 1
                $category->parent_id = null;
                $category->level = 1;
                return;
            }

            $category->level = (int) $parent->level + 1;

            // Enforce max 3 levels
            if ($category->level > 3) {
                throw new \RuntimeException('You can only create up to 3 category levels.');
            }
        });

        // Optional but recommended:
        // Prevent assigning parent to itself
        static::saving(function (Category $category) {
            if ($category->parent_id && $category->parent_id == $category->id) {
                throw new \RuntimeException('A category cannot be its own parent.');
            }
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    // public function children(): HasMany
    // {
    //     return $this->hasMany(Category::class, 'parent_id');
    // }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('name');
    }

}
