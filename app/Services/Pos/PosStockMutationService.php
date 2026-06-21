<?php

namespace App\Services\Pos;

use App\Enums\PosStockMutationType;
use App\Models\PosProduct;
use App\Models\PosStockMutation;
use Illuminate\Database\Eloquent\Model;

class PosStockMutationService
{
    public function create(
        PosProduct $product,
        PosStockMutationType $type,
        int $quantity,
        int $stockBefore,
        int $stockAfter,
        ?string $notes = null,
        int $companyId,
        ?Model $reference,
        int $createdBy,
    ): PosStockMutation {
        return PosStockMutation::create([
            'type'         => $type,
            'quantity'     => $quantity,
            'stock_before' => $stockBefore,
            'stock_after'  => $stockAfter,
            'notes'        => $notes,
            'product_id'   => $product->id,
            'company_id'   => $companyId,
            'reference_id' => $reference?->id,
            'created_by'   => $createdBy,
        ]);
    }

    public function adjustStock(PosProduct $product, int $newStock): void
    {
        $product->update(['stock' => max(0, $newStock)]);
    }
}
