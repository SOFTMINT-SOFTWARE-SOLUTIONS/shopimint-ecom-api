<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    // GET /api/v1/categories
    public function index(Request $request)
    {
        // If you want only parents, keep query param support
        $onlyParents = $request->boolean('only_parents');

        $q = Category::query()
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($onlyParents) {
            $q->whereNull('parent_id');
        } else {
            // default: start from parents and include children
            $q->whereNull('parent_id')->with([
                'children' => fn ($cq) => $cq->with('children'), // 2 levels
            ]);
        }

        $cats = $q->get([
            'id','parent_id','level','name','slug','description','image_url','sort_order'
        ]);

        // If only_parents=1, return flat list (existing behavior)
        if ($onlyParents) {
            return response()->json([
                'status' => true,
                'data' => $cats->map(fn ($c) => $this->transform($c, false))->values(),
            ]);
        }

        // Nested tree
        return response()->json([
            'status' => true,
            'data' => $cats->map(fn ($c) => $this->transform($c, true))->values(),
        ]);
    }

    // GET /api/v1/categories/{slug}
    public function show(string $slug)
    {
        $cat = Category::query()
            ->where('slug', $slug)
            ->where('is_active', 1)
            ->with([
                'children' => fn ($cq) => $cq->with('children'),
            ])
            ->firstOrFail([
                'id','parent_id','level','name','slug','description','image_url','sort_order'
            ]);

        return response()->json(['status' => true, 'data' => $this->transform($cat, true)]);
    }

    private function transform(Category $c, bool $withChildren): array
    {
        // ✅ image_url is your icon now
        // If image_url already stores full URL, keep it
        // If it stores a relative path, convert to full using asset()
        $iconUrl = null;
        if (!empty($c->image_url)) {
            $iconUrl = str_starts_with($c->image_url, 'http')
                ? $c->image_url
                : asset($c->image_url);
        }

        return [
            'id' => $c->id,
            'parent_id' => $c->parent_id,
            'level' => $c->level,
            'name' => $c->name,
            'slug' => $c->slug,
            'description' => $c->description,
            'image_url' => $iconUrl,
            'sort_order' => $c->sort_order,
            'children' => $withChildren
                ? ($c->children?->map(fn ($ch) => $this->transform($ch, true))->values()->all() ?? [])
                : [],
        ];
    }
}