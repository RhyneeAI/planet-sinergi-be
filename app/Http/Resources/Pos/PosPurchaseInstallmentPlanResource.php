<?php

namespace App\Http\Resources\Pos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosPurchaseInstallmentPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ulid'           => (string) $this->ulid,
            'status'         => $this->status->value,
            'status_label'   => $this->status->label(),
            'total_amount'   => $this->total_amount,
            'paid_amount'    => $this->paid_amount,
            'remaining'      => $this->remainingAmount(),
            'tenor'          => $this->tenor,
            'start_date'     => $this->start_date?->toDateString(),
            'supplier'       => $this->whenLoaded('supplier', fn() => [
                'uuid' => $this->supplier->uuid,
                'name' => $this->supplier->name,
            ]),
            'transaction'    => $this->whenLoaded('purchaseTransaction', fn() => [
                'ulid'             => (string) $this->purchaseTransaction->ulid,
                'transaction_code' => $this->purchaseTransaction->transaction_code,
                'transaction_date' => $this->purchaseTransaction->transaction_date?->toDateString(),
            ]),
            'payments'       => $this->whenLoaded('payments', fn() =>
                $this->payments->map(fn($p) => [
                    // 'ulid'               => (string) $p->ulid,
                    'installment_number' => $p->installment_number,
                    'paid_amount'        => $p->paid_amount,
                    'paid_date'          => $p->paid_date?->toDateString(),
                    'notes'              => $p->notes,
                ])
            ),
            'payments_count' => $this->whenLoaded('payments', fn() => $this->payments->count()),
            'created_at'     => $this->created_at?->toISOString(),
        ];
    }
}
