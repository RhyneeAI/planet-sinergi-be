<?php

namespace App\Http\Controllers\Api;

use App\Enums\StockMutationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMutation;
use App\Models\Unit;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    protected array $sortableColumns = ['name', 'code', 'sales_price', 'stock', 'created_at'];

    public function generateCode(Request $request)
    {
        $user = $request->user();
        $company = $user->company;
        
        // Get all codes for this company
        $products = Product::where('company_id', $company->id)
            ->where('code', 'LIKE', $company->code . '%')
            ->get();
        
        $maxSequence = 0;
        foreach ($products as $product) {
            // Extract number after company code (e.g., "MJ001" → 1)
            $code = $product->code;
            $number = (int) substr($code, strlen($company->code));
            if ($number > $maxSequence) {
                $maxSequence = $number;
            }
        }
        
        $sequence = $maxSequence + 1;
        $code = sprintf('%s%04d', $company->code, $sequence); // MJ001, MJ002, etc
        
        return response()->json([
            'success' => true,
            'message' => 'Product code generated',
            'data' => ['code' => $code]
        ]);
    }

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
                // Case-insensitive search using LOWER() for PostgreSQL and MySQL compatibility
                $query->where(function ($q) use ($search) {
                    $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%'])
                        ->orWhereRaw('LOWER(code) LIKE ?', ['%' . strtolower($search) . '%']);
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
        $category = $request->category_uuid
            ? Category::where('uuid', $request->category_uuid)->first()
            : null;
        $unit = $request->unit_uuid
            ? Unit::where('uuid', $request->unit_uuid)->first()
            : null;

        $stock = $request->stock ?? 0;

        $product = Product::create([
            'name'                => $request->name,
            'code'                => $request->code,
            'base_price'          => $request->base_price ?? 0,
            'sales_price'         => $request->sales_price,
            'last_purchase_price' => $request->last_purchase_price ?? 0,
            'stock'               => $stock,
            'min_stock'           => $request->min_stock ?? 0,
            'description'         => $request->description,
            'is_active'           => $request->is_active ?? true,
            'category_id'         => $category?->id,
            'unit_id'             => $unit?->id,
            'created_by'          => $request->user()->id,
            'company_id'          => $request->user()->company_id,
        ]);

        StockMutation::create([
            'type'         => StockMutationType::ADJUST_IN,
            'quantity'     => $stock,
            'stock_before' => 0,
            'stock_after'  => $stock,
            'notes'        => "Mutasi awal produk",
            'product_id'   => $product->id,
            'company_id'   => $request->user()->company_id,
            'reference_id' => null,
            'created_by'   => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('products.stored'),
            'data'    => new ProductResource($product->load(['category', 'unit', 'createdBy'])),
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
        $category = $request->has('category_uuid') 
            ? Category::where('uuid', $request->category_uuid)->first() 
            : null;
        $unit = $request->has('unit_uuid') 
            ? Unit::where('uuid', $request->unit_uuid)->first() 
            : null;

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
            'category_id' => $category?->id,
            'unit_id' => $unit?->id,
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