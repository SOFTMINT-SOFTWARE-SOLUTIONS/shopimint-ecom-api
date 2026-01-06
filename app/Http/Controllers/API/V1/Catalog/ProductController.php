<?php

namespace App\Http\Controllers\API\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function show(Request $request, string $slug)
    {
        $include = collect(explode(',', (string) $request->query('include', '')))
            ->filter()
            ->map(fn ($s) => trim($s))
            ->values();

        $cacheKey = 'api:v1:product:slug:' . $slug . ':inc:' . $include->implode(',');

        $payload = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($slug, $include) {

            $product = Product::query()
                ->where('slug', $slug)
                ->where('status', 'active') // adjust if you use is_active
                ->with([
                    'brand',
                    'category',
                    'variants' => function ($q) {
                        $q->where('is_active', 1)
                          ->orderByDesc('is_default')
                          ->orderBy('id');
                    },
                ])
                ->first();

            if (!$product) {
                return null;
            }

            // --- Price min/max from variants (no SQL subquery needed) ---
            $activeVariants = $product->variants;

            $priceMin = $activeVariants->min('price');
            $priceMax = $activeVariants->max('price');

            // --- Stock total from inventory_levels (main location) ---
            // Assumptions (based on our inventory design):
            // inventory_items: id, variant_id
            // inventory_levels: inventory_item_id, location_id, available, reserved
            // If your column names differ, tell me and I’ll adjust.
            $variantIds = $activeVariants->pluck('id')->all();

            $mainLocationId = 1; // ✅ set your main location id (or fetch from config/table)

            $stockByVariant = [];
            if (!empty($variantIds)) {
                $rows = DB::table('inventory_levels')
                    ->join('inventory_items', 'inventory_items.id', '=', 'inventory_levels.inventory_item_id')
                    ->whereIn('inventory_items.variant_id', $variantIds)
                    ->where('inventory_levels.location_id', $mainLocationId)
                    ->select([
                        'inventory_items.variant_id as variant_id',
                        DB::raw('(inventory_levels.available - inventory_levels.reserved) as free_stock'),
                    ])
                    ->get();

                foreach ($rows as $r) {
                    $stockByVariant[(int)$r->variant_id] = max(0, (int)$r->free_stock);
                }
            }

            $variantStockTotal = array_sum($stockByVariant);

            // ---- Product images (Spatie media library support) ----
            $images = [];
            if (method_exists($product, 'getMedia')) {
                $images = $product->getMedia('product_images')->map(fn ($m) => [
                    'id' => $m->id,
                    'url' => $m->getFullUrl(),
                    'name' => $m->name,
                ])->values()->all();
            } elseif (isset($product->images) && is_array($product->images)) {
                $images = $product->images;
            }

            // ---- Variants payload + stock ----
            $variants = $activeVariants->map(function ($v) use ($stockByVariant) {
                $variantImages = [];

                if (method_exists($v, 'getMedia')) {
                    $variantImages = $v->getMedia('variant_image')->map(fn ($m) => [
                        'id' => $m->id,
                        'url' => $m->getFullUrl(),
                        'name' => $m->name,
                    ])->values()->all();
                }

                $freeStock = $stockByVariant[$v->id] ?? 0;

                return [
                    'id' => $v->id,
                    'title' => $v->title,
                    'sku' => $v->sku,
                    'price' => (float) $v->price,
                    'compare_at_price' => isset($v->compare_at_price) ? (float) $v->compare_at_price : null,

                    'is_default' => (bool) ($v->is_default ?? false),
                    'is_active' => (bool) ($v->is_active ?? true),

                    // inventory behavior flags (keep if you store them in variants)
                    'track_inventory' => (bool) ($v->track_inventory ?? true),
                    'allow_backorder' => (bool) ($v->allow_backorder ?? false),

                    // ✅ stock comes from inventory_levels
                    'stock' => (int) $freeStock,

                    'options' => $v->options_json ?? ($v->options ?? null),

                    'images' => $variantImages,
                ];
            })->values()->all();

            // Reviews summary (safe if you have the relation)
            $reviewsCount = 0;
            $ratingAvg = null;
            if (method_exists(Product::class, 'reviews')) {
                $product->loadCount('reviews')->loadAvg('reviews', 'rating');
                $reviewsCount = (int) ($product->reviews_count ?? 0);
                $ratingAvg = $product->reviews_avg_rating ? round((float)$product->reviews_avg_rating, 2) : null;
            }

            return [
                'id' => $product->id,
                'slug' => $product->slug,
                'title' => $product->title ?? $product->name ?? null,
                'status' => $product->status ?? 'active',

                'description' => $product->description ?? null,
                'short_description' => $product->short_description ?? null,

                'featured_image' => $product->featured_image
                    ? asset('storage/' . ltrim($product->featured_image, '/'))
                    : null,

                'sell_price' => $product->sell_price !== null ? (float) $product->sell_price : null,
                'compare_price' => $product->compare_price !== null ? (float) $product->compare_price : null,
                'in_stock' => (bool) $product->in_stock,

                'brand' => $product->brand ? [
                    'id' => $product->brand->id,
                    'name' => $product->brand->name ?? null,
                    'slug' => $product->brand->slug ?? null,
                ] : null,

                'category' => $product->category ? [
                    'id' => $product->category->id,
                    'name' => $product->category->name ?? null,
                    'slug' => $product->category->slug ?? null,
                    'parent_id' => $product->category->parent_id ?? null,
                ] : null,

                'price_min' => $priceMin !== null ? (float) $priceMin : null,
                'price_max' => $priceMax !== null ? (float) $priceMax : null,

                'variant_stock' => (int) $variantStockTotal,

                'currency' => $product->currency ?? 'LKR',

                'reviews_count' => $reviewsCount,
                'rating_avg' => $ratingAvg,

                'images' => $images,
                'variants' => $variants,

                'created_at' => optional($product->created_at)->toISOString(),
                'updated_at' => optional($product->updated_at)->toISOString(),
            ];
        });

        if (!$payload) {
            return response()->json(['status' => false, 'message' => 'Product not found'], 404);
        }

        return response()->json(['status' => true, 'data' => $payload]);
    }
}
