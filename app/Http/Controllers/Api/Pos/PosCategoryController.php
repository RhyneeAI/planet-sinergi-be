<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\PosCategoryRequest;
use App\Http\Resources\Pos\PosCategoryResource;
use App\Http\Traits\DataTablesResponse;
use App\Models\PosCategory;
use Illuminate\Http\Request;

class PosCategoryController extends Controller
{
    use DataTablesResponse;

    public function index(Request $request)
    {
        $categories = PosCategory::with('createdBy') 
            ->when($request->search, function ($query, $search) {
                // Case-insensitive search using LOWER() for PostgreSQL and MySQL compatibility
                $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%']);
            })
            ->orderBy('name')
            ->paginate($request->input('per_page', 15));

        return response()->json(
            $this->dataTablesResponse($request, $categories, [
                'success' => true,
                'message' => __('pos.categories.list'),
                'data' => PosCategoryResource::collection($categories),
            ])
        );
    }

    public function store(PosCategoryRequest $request)
    {
        $category = PosCategory::create([
            'name' => $request->name,
            'created_by' => $request->user()->id,
            'company_id' => $request->user()->company_id,
        ]);

        $category->load('createdBy');

        return response()->json([
            'success' => true,
            'message' => __('pos.categories.stored'),
            'data' => new PosCategoryResource($category),
        ], 201);
    }

    public function show(PosCategory $category)
    {
        $category->loadMissing('createdBy');

        return response()->json([
            'success' => true,
            'message' => __('pos.categories.detail'),
            'data' => new PosCategoryResource($category),
        ]);
    }

    public function update(PosCategoryRequest $request, PosCategory $category)
    {
        if ($request->has('name')) {
            $category->update(['name' => $request->name]);
        }

        $category->load('createdBy');

        return response()->json([
            'success' => true,
            'message' => __('pos.categories.updated'),
            'data' => new PosCategoryResource($category),
        ]);
    }

    public function destroy(PosCategory $category)
    {
        if ($category->products()->exists()) {
            return response()->json([
                'success' => false,
                'message' => __('pos.categories.has_products'),
                'code' => 422,
            ], 422);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => __('pos.categories.deleted'),
        ]);
    }
}
