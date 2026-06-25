<?php

namespace App\Services\Pos;

use App\Enums\PosInstallmentStatus;
use App\Enums\PosTransactionStatus;
use App\Models\PosPurchaseInstallmentPlan;
use App\Models\PosSalesInstallmentPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PosInstallmentService
{
    public function paySales(PosSalesInstallmentPlan $plan, float $paidAmount, ?string $notes, User $user): PosSalesInstallmentPlan
    {
        if ($plan->status === PosInstallmentStatus::COMPLETED) {
            throw ValidationException::withMessages([
                'installment' => [__('pos.installments.already_completed')],
            ]);
        }

        $remaining = $plan->remainingAmount();

        if ($paidAmount > $remaining) {
            throw ValidationException::withMessages([
                'paid_amount' => [__('pos.installments.overpaid', ['remaining' => $remaining])],
            ]);
        }

        return DB::transaction(function () use ($plan, $paidAmount, $notes, $user) {
            $plan->payments()->create([
                'ulid'                      => Str::ulid(),
                'sales_installment_plan_id' => $plan->id,
                'paid_amount'               => $paidAmount,
                'paid_date'                 => now()->toDateString(),
                'notes'                     => $notes,
                'created_by'                => $user->id,
                'company_id'                => $user->company_id,
            ]);

            $newPaidAmount = $plan->paid_amount + $paidAmount;
            $isCompleted   = $newPaidAmount >= $plan->total_amount;

            $newStatus = $isCompleted ? PosInstallmentStatus::COMPLETED : PosInstallmentStatus::ACTIVE;

            $plan->update([
                'paid_amount' => $newPaidAmount,
                'status'      => $newStatus,
            ]);

            $plan->salesTransaction->update([
                'transaction_status' => $isCompleted ? PosTransactionStatus::PAID : PosTransactionStatus::PROCESS,
                'paid'               => $isCompleted ? $plan->total_amount : $newPaidAmount,
            ]);

            return $plan->fresh()->load(['customer', 'salesTransaction', 'salesTransaction.createdBy', 'payments']);
        });
    }

    public function payPurchase(PosPurchaseInstallmentPlan $plan, float $paidAmount, ?string $notes, User $user): PosPurchaseInstallmentPlan
    {
        if ($plan->status === PosInstallmentStatus::COMPLETED) {
            throw ValidationException::withMessages([
                'installment' => [__('pos.installments.already_completed')],
            ]);
        }

        $remaining = $plan->remainingAmount();

        if ($paidAmount > $remaining) {
            throw ValidationException::withMessages([
                'paid_amount' => [__('pos.installments.overpaid', ['remaining' => $remaining])],
            ]);
        }

        return DB::transaction(function () use ($plan, $paidAmount, $notes, $user) {
            $nextNumber = $plan->payments()->count() + 1;

            $plan->payments()->create([
                'ulid'                         => Str::ulid(),
                'purchase_installment_plan_id' => $plan->id,
                'installment_number'           => $nextNumber,
                'paid_amount'                  => $paidAmount,
                'paid_date'                    => now()->toDateString(),
                'notes'                        => $notes,
                'created_by'                   => $user->id,
                'company_id'                   => $user->company_id,
            ]);

            $newPaidAmount = $plan->paid_amount + $paidAmount;
            $isCompleted   = $newPaidAmount >= $plan->total_amount;

            $newStatus = $isCompleted ? PosInstallmentStatus::COMPLETED : PosInstallmentStatus::ACTIVE;

            $plan->update([
                'paid_amount' => $newPaidAmount,
                'status'      => $newStatus,
            ]);

            $plan->purchaseTransaction->update([
                'transaction_status' => $isCompleted ? PosTransactionStatus::PAID : PosTransactionStatus::PROCESS,
                'paid'               => $isCompleted ? $plan->total_amount : $newPaidAmount,
            ]);

            return $plan->fresh()->load(['supplier', 'purchaseTransaction', 'payments']);
        });
    }
}
