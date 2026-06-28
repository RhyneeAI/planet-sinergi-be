<?php

namespace App\Http\Resources\Absence;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AbsEmployeeDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $profile = $this->absEmployeeProfile;

        return [
            'uuid' => (string) $this->uuid,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'role' => $this->role->value,
            'is_active' => $this->is_active,
            'profile' => $profile ? [
                'jabatan' => $profile->jabatan ? [
                    'uuid' => (string) $profile->jabatan->uuid,
                    'name' => $profile->jabatan->name,
                    'daily_rate' => (float) $profile->jabatan->daily_rate,
                ] : null,
                'sub_company' => $profile->subCompany ? [
                    'uuid' => (string) $profile->subCompany->uuid,
                    'name' => $profile->subCompany->name,
                    'code' => $profile->subCompany->code,
                ] : null,
                'shift' => $profile->shift ? [
                    'uuid' => (string) $profile->shift->uuid,
                    'name' => $profile->shift->name,
                ] : null,
            ] : null,
            'overtimes' => $this->relationLoaded('absOvertimes')
                ? $this->absOvertimes->map(fn($o) => [
                    'id' => $o->id,
                    'date' => $o->date->toDateString(),
                    'start_time' => substr((string) $o->start_time, 0, 5),
                    'end_time' => substr((string) $o->end_time, 0, 5),
                    'reason' => $o->reason,
                    'status' => $o->status->value,
                ])->values()
                : [],
            'loans' => $this->relationLoaded('absLoans')
                ? $this->absLoans->map(fn($l) => [
                    'id' => $l->id,
                    'amount' => (float) $l->amount,
                    'reason' => $l->reason,
                    'tenor_months' => (int) $l->tenor_months,
                    'monthly_installment' => (float) $l->monthly_installment,
                    'remaining_balance' => (float) $l->remaining_balance,
                    'status' => $l->status->value,
                ])->values()
                : [],
            'payrolls' => $this->relationLoaded('absPayrollPeriods')
                ? $this->absPayrollPeriods->map(fn($p) => [
                    'ulid' => (string) $p->ulid,
                    'period_month' => $p->period_month,
                    'period_year' => $p->period_year,
                    'daily_rate' => (float) $p->daily_rate,
                    'total_days' => (int) $p->total_days,
                    'gross_salary' => (float) $p->gross_salary,
                    'total_deduction' => (float) $p->total_deduction,
                    'total_bonus' => (float) $p->total_bonus,
                    'net_salary' => (float) $p->net_salary,
                    'status' => $p->status->value,
                ])->values()
                : [],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
