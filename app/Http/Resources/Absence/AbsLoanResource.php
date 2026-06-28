<?php

namespace App\Http\Resources\Absence;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AbsLoanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', fn() => [
                'uuid' => (string) $this->user->uuid,
                'name' => $this->user->name,
                'phone' => $this->user->phone,
            ]),
            'amount' => (float) $this->amount,
            'reason' => $this->reason,
            'tenor_months' => (int) $this->tenor_months,
            'monthly_installment' => (float) $this->monthly_installment,
            'remaining_balance' => (float) $this->remaining_balance,
            'status' => $this->status->value,
            'approved_by' => $this->whenLoaded('approver', fn() => $this->approver?->name),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
