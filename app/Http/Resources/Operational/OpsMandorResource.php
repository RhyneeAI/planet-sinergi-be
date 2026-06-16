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

        $primarySubCompany = $this->relationLoaded('subCompanies')
            ? $this->subCompanies->first()
            : null;

        $hasSubCompany = $primarySubCompany !== null;

        return [
            'uuid' => (string) $this->uuid,
            'name' => $this->name,
            'username' => $this->generatedUsername(),
            'phone' => $this->phone,
            'is_active' => (bool) $this->is_active,
            'has_sub_company' => $hasSubCompany,
            'sub_company' => $hasSubCompany ? [
                'uuid' => (string) $primarySubCompany->uuid,
                'name' => $primarySubCompany->name,
                'code' => $primarySubCompany->code,
            ] : null,
            'sub_companies' => $this->whenLoaded('subCompanies', function () {
                return $this->subCompanies->map(fn ($subCompany) => [
                    'uuid' => (string) $subCompany->uuid,
                    'name' => $subCompany->name,
                    'code' => $subCompany->code,
                ])->values();
            }),
            $this->mergeWhen($request->boolean('is_dashboard_data'), [
                'total_income' => $income,
                'total_expense' => $expense,
                'balance' => $balance,
                'status' => $balance <= 0 ? 'Saldo Habis' : 'Aktif',
            ]),
        ];
    }

    protected function generatedUsername(): string
    {
        return strtolower(preg_replace('/\s+/', '', $this->name) ?? '');
    }
}
