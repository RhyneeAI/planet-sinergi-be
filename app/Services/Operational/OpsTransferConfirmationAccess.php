<?php

namespace App\Services\Operational;

use App\Models\OpsExpense;
use App\Models\OpsIncome;
use App\Models\OpsTransferConfirmation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class OpsTransferConfirmationAccess
{
    public function resolveIncome(OpsTransferConfirmation $confirmation): ?OpsIncome
    {
        $confirmation->loadMissing('confirmable');
        $confirmable = $confirmation->confirmable;

        if ($confirmable instanceof OpsIncome) {
            return $confirmable;
        }

        if ($confirmable instanceof OpsExpense) {
            return $confirmable->transferIncome;
        }

        if ($confirmation->confirmable_type !== 'ops_incomes') {
            return null;
        }

        return OpsIncome::query()
            ->where('id', $confirmation->confirmable_id)
            ->first();
    }

    public function mandorCanAccessIncome(User $mandor, OpsIncome $income): bool
    {
        if ((int) $income->mandor_id === (int) $mandor->id) {
            return true;
        }

        $income->loadMissing('subCompany');

        return (int) $income->subCompany?->mandor_id === (int) $mandor->id;
    }

    public function scopeForMandor(Builder $query, User $mandor): Builder
    {
        return $query->whereHasMorph(
            'confirmable',
            [OpsExpense::class, OpsIncome::class],
            fn (Builder $incomeQuery) => $incomeQuery->where(function (Builder $inner) use ($mandor) {
                $inner->where('mandor_id', $mandor->id)
                    ->orWhereHas(
                        'subCompany',
                        fn (Builder $subCompanyQuery) => $subCompanyQuery->where('mandor_id', $mandor->id)
                    );
            })
        );
    }
}
