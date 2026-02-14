<?php

namespace App\Http\Controllers\API\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    /**
     * GET /api/v1/search?q=iphone&page=1&per_page=24
     */
    public function search(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(60, (int) $request->query('per_page', 24)));

        if ($q === '' || mb_strlen($q) < 2) {
            return response()->json([
                'status' => true,
                'data' => [
                    'items' => [],
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $perPage,
                        'total' => 0,
                        'last_page' => 1,
                    ]
                ]
            ]);
        }

        // Cache key per query + page + perPage
        $cacheKey = 'api:v1:search:' . md5(json_encode([
            'q' => $q,
            'page' => $page,
            'per_page' => $perPage,
        ]));

        $payload = Cache::remember($cacheKey, now()->addSeconds(30), function () use ($q, $perPage) {

            // IMPORTANT:
            // - We join brand/category for searching their names
            // - We also search variants.sku via exists subquery (fast if indexed)

            $query = Product::query()
                ->where('products.status', 'active')
                ->leftJoin('brands', 'brands.id', '=', 'products.brand_id')
                ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
                ->select('products.*') // avoid duplicate column conflicts
                ->where(function ($qq) use ($q) {
                    $qq->where('products.title', 'like', "%{$q}%")
                        ->orWhere('products.slug', 'like', "%{$q}%")
                        ->orWhere('products.short_description', 'like', "%{$q}%")
                        ->orWhere('brands.name', 'like', "%{$q}%")
                        ->orWhere('categories.name', 'like', "%{$q}%")
                        ->orWhereExists(function ($sub) use ($q) {
                            $sub->select(DB::raw(1))
                                ->from('variants')
                                ->whereColumn('variants.product_id', 'products.id')
                                ->where('variants.is_active', 1)
                                ->where('variants.sku', 'like', "%{$q}%");
                        });
                })
                ->with(['brand', 'category'])
                ->orderByDesc('products.id');

            $paginator = $query->paginate($perPage);

            $items = $paginator->getCollection()
                ->map(fn ($p) => $this->miniProduct($p))
                ->values()
                ->all();

            return [
                'items' => $items,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ]
            ];
        });

        return response()->json([
            'status' => true,
            'data' => $payload,
        ]);
    }

    /**
     * Same mini payload used in ProductController for listings
     */
    private function miniProduct(Product $product): array
    {
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

        return [
            'id' => $product->id,
            'slug' => $product->slug,
            'title' => $product->title ?? $product->name ?? null,
            'status' => $product->status ?? 'active',
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

            'currency' => $product->currency ?? 'LKR',
            'images' => $images,
        ];
    }
}