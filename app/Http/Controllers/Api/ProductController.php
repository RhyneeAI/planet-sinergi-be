<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    protected array $sortableColumns = ['name', 'code', 'sales_price', 'stock', 'created_at'];

    public function index(Request $request)
    {
        // Sorting
        $orderByKey = in_array($request->input('order_by_key', 'name'), $this->sortableColumns)
            ? $request->input('order_by_key', 'name')
            : 'name';
        $orderByValue = strtoupper($request->input('order_by_value', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

        $products = Product::query()
            ->with(['category', 'unit'])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->orderBy($orderByKey, $orderByValue)
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => __('products.list'),
            'data' => ProductResource::collection($products),
        ]);
    }

    public function store(ProductRequest $request)
    {
        $product = Product::create([
            'name' => $request->name,
            'code' => $request->code,
            'base_price' => $request->base_price ?? 0,
            'sales_price' => $request->sales_price,
            'last_purchase_price' => $request->last_purchase_price ?? 0,
            'stock' => $request->stock ?? 0,
            'min_stock' => $request->min_stock ?? 0,
            'description' => $request->description,
            'is_active' => $request->is_active ?? true,
            'category_id' => $request->category_id,
            'unit_id' => $request->unit_id,
            'created_by' => $request->user()->id,
            'company_id' => $request->user()->company_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('products.stored'),
            'data' => new ProductResource($product->load(['category', 'unit', 'createdBy'])),
        ], 201);
    }

    public function show(Product $product)
    {
        return response()->json([
            'success' => true,
            'message' => __('products.detail'),
            'data' => new ProductResource($product->load(['category', 'unit', 'createdBy'])),
        ]);
    }

    public function update(ProductRequest $request, Product $product)
    {
        $data = array_filter([
            'name' => $request->has('name') ? $request->name : null,
            'code' => $request->has('code') ? $request->code : null,
            'base_price' => $request->has('base_price') ? $request->base_price : null,
            'sales_price' => $request->has('sales_price') ? $request->sales_price : null,
            'last_purchase_price' => $request->has('last_purchase_price') ? $request->last_purchase_price : null,
            'stock' => $request->has('stock') ? $request->stock : null,
            'min_stock' => $request->has('min_stock') ? $request->min_stock : null,
            'description' => $request->has('description') ? $request->description : null,
            'is_active' => $request->has('is_active') ? $request->is_active : null,
            'category_id' => $request->has('category_id') ? $request->category_id : null,
            'unit_id' => $request->has('unit_id') ? $request->unit_id : null,
        ], fn($value) => !is_null($value));

        $product->update($data);

        return response()->json([
            'success' => true,
            'message' => __('products.updated'),
            'data' => new ProductResource($product->load(['category', 'unit', 'createdBy'])),
        ]);
    }

    public function destroy(Product $product)
    {
        $hasSalesDetails = $product->salesDetails()->exists();
        $hasPurchaseDetails = $product->purchaseDetails()->exists();
        $hasMarketingProducts = $product->marketingProducts()->exists();

        if ($hasSalesDetails || $hasPurchaseDetails || $hasMarketingProducts) {
            return response()->json([
                'success' => false,
                'message' => __('products.has_relations'),
                'code' => 422,
            ], 422);
        }

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => __('products.deleted'),
        ]);
    }
}