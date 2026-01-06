<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategoryProductController extends Controller
{
    // GET /api/v1/categories/{slug}/products?include_children=1
    public function index(Request $request, string $slug)
    {
        $category = Category::where('slug', $slug)->where('is_active', 1)->firstOrFail();

        $ids = [$category->id];

        if ($request->boolean('include_children')) {
            $childIds = Category::where('parent_id', $category->id)
                ->where('is_active', 1)
                ->pluck('id')
                ->toArray();
            $ids = array_merge($ids, $childIds);
        }

        $q = Product::query()
            ->where('status', 'active')
            ->whereIn('category_id', $ids)
            ->withMin(['variants as price_min' => fn($v) => $v->where('is_active', 1)], 'price')
            ->withMax(['variants as price_max' => fn($v) => $v->where('is_active', 1)], 'price')
            ->select('products.*')
            ->selectSub(
                DB::table('variants')
                    ->join('inventory_items', 'inventory_items.variant_id', '=', 'variants.id')
                    ->join('inventory_levels', 'inventory_levels.inventory_item_id', '=', 'inventory_items.id')
                    ->whereColumn('variants.product_id', 'products.id')
                    ->selectRaw('COALESCE(SUM(inventory_levels.available - inventory_levels.reserved), 0)'),
                'stock_available'
            )
            ->latest('products.created_at');

        $products = $q->paginate((int)($request->get('per_page', 20)));

        $data = collect($products->items())->map(fn($p) => [
            'id' => $p->id,
            'title' => $p->title,
            'slug' => $p->slug,
            'product_type' => $p->product_type,
            'featured' => (int)$p->featured,
            'price_min' => $p->price_min,
            'price_max' => $p->price_max,
            'stock_available' => (int)($p->stock_available ?? 0),
            'in_stock' => ((int)($p->stock_available ?? 0) > 0),
        ]);

        return response()->json([
            'status' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'total' => $products->total(),
            ],
        ]);
    }
}
