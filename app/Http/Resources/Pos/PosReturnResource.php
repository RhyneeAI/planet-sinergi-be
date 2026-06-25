<?php

namespace App\Http\Resources\Pos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosReturnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ulid'          => (string) $this->ulid,
            'qty'           => $this->qty,
            'reason'        => $this->reason,
            'refund_amount' => $this->refund_amount,
            'status'        => $this->status,
            'created_at'    => $this->created_at?->toISOString(),
            'product'       => $this->whenLoaded('product', fn() => [
                'uuid' => $this->product->uuid,
                'name' => $this->product->name,
                'code' => $this->product->code,
            ]),
            'transaction'   => $this->whenLoaded('salesTransaction', fn() => [
                'ulid'             => (string) $this->salesTransaction->ulid,
                'transaction_code' => $this->salesTransaction->transaction_code,
                'transaction_date' => $this->salesTransaction->transaction_date?->toDateString(),
            ]),
            'created_by'    => $this->whenLoaded('createdBy', fn() => [
                'uuid' => $this->createdBy->uuid,
                'name' => $this->createdBy->name,
            ]),
        ];
    }
}
