<?php

namespace App\Http\Controllers\API\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    // ✅ change this to your main inventory location
    private int $mainLocationId = 1;

    /**
     * Single product (your original)
     */
    public function show(Request $request, string $slug)
    {
        $include = collect(explode(',', (string) $request->query('include', '')))
            ->filter()
            ->map(fn ($s) => trim($s))
            ->values();

        $cacheKey = 'api:v1:product:slug:' . $slug . ':inc:' . $include->implode(',');

        $payload = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($slug) {

            $product = Product::query()
                ->where('slug', $slug)
                ->where('status', 'active')
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

            if (!$product) return null;

            return $this->transformProduct($product);
        });

        if (!$payload) {
            return response()->json(['status' => false, 'message' => 'Product not found'], 404);
        }

        return response()->json(['status' => true, 'data' => $payload]);
    }

    /**
     * ✅ List products (supports category filter including children)
     * GET /api/v1/products?category=phones  (slug OR id)
     * optional: ?page=1&per_page=24
     */
    public function index(Request $request)
    {
        $category = $request->query('category'); // slug or id
        $perPage  = max(1, min(60, (int) $request->query('per_page', 24)));
        $page     = max(1, (int) $request->query('page', 1));

        $cacheKey = 'api:v1:products:index:' . md5(json_encode($request->query()));

        $payload = Cache::remember($cacheKey, now()->addMinutes(3), function () use ($category, $perPage) {

            $q = Product::query()
                ->where('status', 'active')
                ->with([
                    'brand',
                    'category'
                ])
                ->orderByDesc('id');

            // ✅ category filter (parent includes all children)
            if (!empty($category)) {
                $categoryIds = $this->resolveCategoryIdsWithChildren($category);
                if (!empty($categoryIds)) {
                    $q->whereIn('category_id', $categoryIds);
                } else {
                    // category not found => return empty
                    return [
                        'items' => [],
                        'pagination' => [
                            'current_page' => 1,
                            'per_page' => $perPage,
                            'total' => 0,
                            'last_page' => 1,
                        ]
                    ];
                }
            }

            $paginator = $q->paginate($perPage);

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

        return response()->json(['status' => true, 'data' => $payload]);
    }

    /**
     * ✅ Featured products
     * GET /api/v1/products/featured?per_page=12
     */
    public function featured(Request $request)
    {
        $perPage = max(1, min(60, (int) $request->query('per_page', 12)));
        $cacheKey = 'api:v1:products:featured:' . $perPage;

        $payload = Cache::remember($cacheKey, now()->addMinutes(3), function () use ($perPage) {

            $paginator = Product::query()
                ->where('status', 'active')
                ->where('featured', 1) // ✅ change if your column differs (e.g. is_featured)
                ->with([
                    'brand',
                    'category'
                ])
                ->orderByDesc('id')
                ->paginate($perPage);

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

        return response()->json(['status' => true, 'data' => $payload]);
    }

    /**
     * ✅ Top selling products
     * GET /api/v1/products/top-selling?days=30&per_page=12
     *
     * Assumes:
     * - order_items: product_id, quantity
     * - orders: id, status, created_at
     * Change table/column names if yours differ.
     */
    public function topSelling(Request $request)
    {
        $days    = max(1, min(365, (int) $request->query('days', 30)));
        $perPage = max(1, min(60, (int) $request->query('per_page', 12)));

        $cacheKey = "api:v1:products:top-selling:days:$days:pp:$perPage";

        $payload = Cache::remember($cacheKey, now()->addMinutes(3), function () use ($days, $perPage) {

            $from = now()->subDays($days);

            // top product ids by qty sold
            $topIds = DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.created_at', '>=', $from)
                ->whereIn('orders.status', ['paid', 'completed', 'delivered']) // ✅ adjust to your statuses
                ->groupBy('order_items.product_id')
                ->orderByRaw('SUM(order_items.quantity) DESC')
                ->limit($perPage)
                ->pluck('order_items.product_id')
                ->map(fn ($id) => (int)$id)
                ->all();

            if (empty($topIds)) {
                return [
                    'items' => [],
                    'pagination' => [
                        'current_page' => 1,
                        'per_page' => $perPage,
                        'total' => 0,
                        'last_page' => 1,
                    ]
                ];
            }

            // fetch products in same order as $topIds
            $products = Product::query()
                ->where('status', 'active')
                ->whereIn('id', $topIds)
                ->with([
                    'brand',
                    'category'
                ])
                ->get()
                ->keyBy('id');

            $ordered = collect($topIds)
                ->map(fn ($id) => $products->get($id))
                ->filter()
                ->values();

            $items = $ordered->map(fn ($p) => $this->miniProduct($p))->values()->all();

            return [
                'items' => $items,
                'meta' => [
                    'range_days' => $days,
                ]
            ];
        });

        return response()->json(['status' => true, 'data' => $payload]);
    }

    /**
     * ✅ Transform product into your payload (reused for lists)
     */
    private function transformProduct(Product $product): array
    {
        $activeVariants = $product->variants ?? collect();

        $priceMin = $activeVariants->min('price');
        $priceMax = $activeVariants->max('price');

        // stock from inventory_levels (main location)
        $variantIds = $activeVariants->pluck('id')->all();
        $stockByVariant = [];

        if (!empty($variantIds)) {
            $rows = DB::table('inventory_levels')
                ->join('inventory_items', 'inventory_items.id', '=', 'inventory_levels.inventory_item_id')
                ->whereIn('inventory_items.variant_id', $variantIds)
                ->where('inventory_levels.location_id', $this->mainLocationId)
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

        // product images
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

        // variants payload
        $variants = $activeVariants->map(function ($v) use ($stockByVariant) {
            $variantImages = [];

            if (method_exists($v, 'getMedia')) {
                $variantImages = $v->getMedia('variant_image')->map(fn ($m) => [
                    'id' => $m->id,
                    'url' => $m->getFullUrl(),
                    'name' => $m->name,
                ])->values()->all();
            }

            return [
                'id' => $v->id,
                'title' => $v->title,
                'sku' => $v->sku,
                'price' => (float) $v->price,
                'compare_at_price' => isset($v->compare_at_price) ? (float) $v->compare_at_price : null,
                'is_default' => (bool) ($v->is_default ?? false),
                'is_active' => (bool) ($v->is_active ?? true),
                'track_inventory' => (bool) ($v->track_inventory ?? true),
                'allow_backorder' => (bool) ($v->allow_backorder ?? false),
                'stock' => (int) ($stockByVariant[$v->id] ?? 0),
                'options' => $v->options_json ?? ($v->options ?? null),
                'images' => $variantImages,
            ];
        })->values()->all();

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

            'images' => $images,
            'variants' => $variants,

            'created_at' => optional($product->created_at)->toISOString(),
            'updated_at' => optional($product->updated_at)->toISOString(),
        ];
    }


    /**
     * ✅ Transform product into your payload (reused for lists)
     */
    private function miniProduct(Product $product): array
    {
        // product images
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

    /**
     * ✅ Get category IDs including all descendants
     * Accepts slug OR id.
     *
     * Uses MySQL 8+ recursive CTE (fast). If your DB is MySQL 5.7, tell me and I’ll give a non-CTE fallback.
     */
    private function resolveCategoryIdsWithChildren(string $category): array
    {
        $cat = DB::table('categories')
            ->when(is_numeric($category), fn ($q) => $q->where('id', (int)$category))
            ->when(!is_numeric($category), fn ($q) => $q->where('slug', $category))
            ->first();

        if (!$cat) return [];

        $rootId = (int) $cat->id;

        // Recursive CTE to fetch all descendants
        $rows = DB::select("
            WITH RECURSIVE cte AS (
                SELECT id, parent_id FROM categories WHERE id = ?
                UNION ALL
                SELECT c.id, c.parent_id
                FROM categories c
                INNER JOIN cte ON c.parent_id = cte.id
            )
            SELECT id FROM cte
        ", [$rootId]);

        return collect($rows)->pluck('id')->map(fn ($id) => (int)$id)->all();
    }
}
