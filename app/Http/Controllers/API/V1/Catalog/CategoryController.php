<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    // GET /api/v1/categories?only_parents=1
    public function index(Request $request)
    {
        $q = Category::query()
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($request->boolean('only_parents')) {
            $q->whereNull('parent_id');
        }

        $categories = $q->get(['id','parent_id','name','slug','sort_order']);

        return response()->json(['status' => true, 'data' => $categories]);
    }

    // GET /api/v1/categories/{slug}
    public function show(string $slug)
    {
        $category = Category::where('slug', $slug)
            ->where('is_active', 1)
            ->firstOrFail(['id','parent_id','name','slug','sort_order']);

        return response()->json(['status' => true, 'data' => $category]);
    }
}
