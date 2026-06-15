<?php

namespace App\Http\Resources\Operational;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpsMandorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $income = (float) ($this->total_income ?? 0);
        $expense = (float) ($this->total_expense ?? 0);
        $balance = max($income - $expense, 0);

        return [
            'uuid' => (string) $this->uuid,
            'name' => $this->name,
            'username' => $this->username,
            'phone' => $this->phone,
            'is_active' => (bool) $this->is_active,

            $this->mergeWhen($request->boolean('is_dashboard_data'), [
                'total_income' => $income,
                'total_expense' => $expense,
                'balance' => $balance,
                'status' => $balance <= 0 ? 'Saldo Habis' : 'Aktif',
            ]),
        ];
    }
}
