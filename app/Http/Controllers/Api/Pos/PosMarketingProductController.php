<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\PosMarketingProductRequest;
use App\Http\Resources\Pos\PosMarketingProductResource;
use App\Http\Traits\DataTablesResponse;
use App\Models\PosMarketingProduct;
use Illuminate\Http\Request;

class PosMarketingProductController extends Controller
{
    use DataTablesResponse;

    protected array $sortableColumns = ['created_at', 'product_name'];

    public function index(Request $request)
    {
        $orderByKey   = in_array($request->input('order_by_key', 'created_at'), $this->sortableColumns)
                            ? $request->input('order_by_key', 'created_at')
                            : 'created_at';
        $orderByValue = strtoupper($request->input('order_by_value', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

        $marketingProducts = PosMarketingProduct::with(['product', 'createdBy', 'marketing'])
            ->join('pos_products', 'pos_products.id', '=', 'pos_marketing_products.product_id')
            ->select('pos_marketing_products.*', 'pos_products.name as product_name') // hindari conflict kolom
            ->when($request->marketing_uuid, function ($query, $marketingUuid) {
                $query->whereHas('marketing', fn($q) =>
                    $q->where('uuid', $marketingUuid)
                );
            })
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->whereRaw('LOWER(pos_products.name) LIKE ?', ["%" . strtolower($search) . "%"])
                    ->orWhereRaw('LOWER(pos_products.code) LIKE ?', ["%" . strtolower($search) . "%"]);
                });
            })
            ->orderBy(
                $orderByKey === 'product_name' ? 'pos_products.name' : $orderByKey,
                $orderByValue
            )
            ->paginate($request->input('per_page', 15));

        return response()->json(
            $this->dataTablesResponse($request, $marketingProducts, [
                'success' => true,
                'message' => __('pos.marketing_product.list'),
                'data'    => PosMarketingProductResource::collection($marketingProducts),
            ])
        );
    }

    public function store(PosMarketingProductRequest $request)
    {
        $marketingProduct = PosMarketingProduct::create([
            'product_id'      => $request->getProductId(),
            'marketing_id'    => $request->getMarketingId(),
            'marketing_price' => $request->marketing_price,
            'created_by'      => $request->user()->id,
            'company_id'      => $request->user()->company_id,
        ]);

        $marketingProduct->load(['product', 'createdBy', 'marketing']);

        return response()->json([
            'success' => true,
            'message' => __('pos.marketing_product.stored'),
            'data'    => new PosMarketingProductResource($marketingProduct->load('product')),
        ], 201);
    }

    public function show(PosMarketingProduct $marketingProduct)
    {
        $marketingProduct->loadMissing(['product', 'createdBy', 'marketing']);

        return response()->json([
            'success' => true,
            'message' => __('pos.marketing_product.detail'),
            'data'    => new PosMarketingProductResource($marketingProduct->load('product')),
        ]);
    }

    public function update(PosMarketingProductRequest $request, PosMarketingProduct $marketingProduct)
    {
        $marketingProduct->update(
            $request->only(['marketing_price'])
        );

        $marketingProduct->load(['product', 'createdBy', 'marketing']);

        return response()->json([
            'success' => true,
            'message' => __('pos.marketing_product.updated'),
            'data'    => new PosMarketingProductResource($marketingProduct->load('product')),
        ]);
    }

    public function destroy(PosMarketingProduct $marketingProduct)
    {
        $marketingProduct->delete();

        return response()->json([
            'success' => true,
            'message' => __('pos.marketing_product.deleted'),
        ]);
    }
}
