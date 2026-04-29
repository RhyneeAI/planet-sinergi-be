<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $categories = Category::orderBy('name')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => __('categories.list'),
            'data'    => CategoryResource::collection($categories),
        ]);
    }

    public function store(CategoryRequest $request)
    {
        $category = Category::create([
            'name'       => $request->name,
            'company_id' => $request->user()->company_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('categories.stored'),
            'data'    => new CategoryResource($category),
        ], 201);
    }

    public function show(Category $category)
    {
        return response()->json([
            'success' => true,
            'message' => __('categories.detail'),
            'data'    => new CategoryResource($category),
        ]);
    }

    public function update(CategoryRequest $request, Category $category)
    {
        if ($request->has('name')) {
            $category->update(['name' => $request->name]);
        }

        return response()->json([
            'success' => true,
            'message' => __('categories.updated'),
            'data'    => new CategoryResource($category),
        ]);
    }

    public function destroy(Category $category)
    {
        if ($category->products()->exists()) {
            return response()->json([
                'success' => false,
                'message' => __('categories.has_products'),
                'code'    => 422,
            ], 422);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => __('categories.deleted'),
        ]);
    }
}