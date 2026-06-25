<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Controller;
use App\Models\PosProduct;
use App\Models\PosStockMutation;
use Illuminate\Http\Request;

class PosStockCardController extends Controller
{
    public function show(Request $request, string $productUuid)
    {
        $product = PosProduct::where('uuid', $productUuid)
            ->where('company_id', $request->user()->company_id)
            ->firstOrFail();

        $mutations = PosStockMutation::where('product_id', $product->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $entries = $mutations->map(function (PosStockMutation $m) {
            return [
                'date'             => $m->created_at->toISOString(),
                'type'             => $m->type->isIncoming() ? 'in' : 'out',
                'mutation_type'    => $m->type->value,
                'mutation_label'   => $m->type->label(),
                'qty_in'           => $m->type->isIncoming() ? (int) $m->quantity : 0,
                'qty_out'          => $m->type->isOutgoing() ? (int) $m->quantity : 0,
                'balance'          => (int) $m->stock_after,
                'notes'            => $m->notes,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => __('pos.stock_card.detail'),
            'data'    => [
                'product' => [
                    'uuid' => $product->uuid,
                    'name' => $product->name,
                    'code' => $product->code,
                    'stock'=> (int) $product->stock,
                ],
                'entries' => $entries,
            ],
        ]);
    }
}
