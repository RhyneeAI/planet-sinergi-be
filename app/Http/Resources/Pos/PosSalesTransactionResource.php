<?php

namespace App\Http\Resources\Pos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosSalesTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ulid'               => (string) $this->ulid,
            'transaction_code'   => $this->transaction_code,
            'transaction_date'   => $this->transaction_date?->toISOString(),
            'discount'           => (float) $this->discount,
            'additional_cost'      => (float) $this->additional_cost,  
            'additional_cost_note' => $this->additional_cost_note, 
            'total'              => (float) $this->total,
            'paid'               => (float) $this->paid,
            'payment_type'       => $this->payment_type?->value,
            'transaction_status' => $this->transaction_status?->value,
            'customer'           => $this->whenLoaded('customer', fn() =>
                $this->customer ? [
                    'name' => $this->customer->name,
                ] : null
            ),
            'created_by'         => $this->whenLoaded('createdBy', fn() => [
                'name' => $this->createdBy->name,
            ]),
            'items'              => $this->whenLoaded('details', fn() =>
                $this->details->map(fn($detail) => [
                    // 'ulid'       => (string) $detail->ulid,
                    'product'    => [
                        // 'uuid' => $detail->product->uuid,
                        'name' => $detail->product->name,
                        'code' => $detail->product->code,
                    ],
                    'quantity'   => (int) $detail->quantity,
                    'marketing_price' => (float) $detail->marketing_price,
                    'sell_price' => (float) $detail->sell_price,
                    'discount'   => (float) $detail->discount,
                    'subtotal'   => (float) $detail->subtotal,
                ])
            ),
            'created_at'         => $this->created_at?->toISOString(),
        ];
    }
}
