<?php

namespace App\Services\Operational;

use App\Enums\OpsWalletTransactionType;
use App\Models\SubCompany;
use App\Models\OpsWallet;
use App\Models\OpsWalletTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class OpsWalletService
{
    public function getOrCreateWallet(User $mandor, SubCompany $subCompany): OpsWallet
    {
        if ($subCompany->mandor_id !== $mandor->id) {
            throw new \InvalidArgumentException('Sub company does not belong to mandor.');
        }

        return OpsWallet::firstOrCreate(
            ['sub_company_id' => $subCompany->id],
            [
                'mandor_id' => $mandor->id,
                'company_id' => $mandor->company_id,
                'balance' => 0,
            ]
        );
    }

    public function credit(
        OpsWallet $wallet,
        float $amount,
        OpsWalletTransactionType $type,
        Model $reference,
        User $actor,
        ?string $note = null
    ): OpsWalletTransaction {
        $balanceBefore = (float) $wallet->balance;
        $balanceAfter = round($balanceBefore + $amount, 2);

        $wallet->update(['balance' => $balanceAfter]);

        return OpsWalletTransaction::create([
            'wallet_id' => $wallet->id,
            'type' => $type,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'reference_type' => $reference->getMorphClass(),
            'reference_id' => $reference->id,
            'note' => $note,
            'created_by' => $actor->id,
            'company_id' => $wallet->company_id,
        ]);
    }

    public function debit(
        OpsWallet $wallet,
        float $amount,
        OpsWalletTransactionType $type,
        Model $reference,
        User $actor,
        ?string $note = null
    ): OpsWalletTransaction {
        $balanceBefore = (float) $wallet->balance;

        if ($balanceBefore < $amount) {
            throw new \RuntimeException(__('operational.wallet.insufficient_balance'));
        }

        $balanceAfter = round($balanceBefore - $amount, 2);

        $wallet->update(['balance' => $balanceAfter]);

        return OpsWalletTransaction::create([
            'wallet_id' => $wallet->id,
            'type' => $type,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'reference_type' => $reference->getMorphClass(),
            'reference_id' => $reference->id,
            'note' => $note,
            'created_by' => $actor->id,
            'company_id' => $wallet->company_id,
        ]);
    }

    public function adjustBalance(
        OpsWallet $wallet,
        float $oldAmount,
        float $newAmount,
        Model $reference,
        User $actor
    ): void {
        $difference = round($newAmount - $oldAmount, 2);

        if ($difference === 0.0) {
            return;
        }

        if ($difference > 0) {
            $this->debit(
                $wallet,
                $difference,
                OpsWalletTransactionType::CASH,
                $reference,
                $actor,
                __('operational.wallet.adjustment_debit')
            );

            return;
        }

        $this->credit(
            $wallet,
            abs($difference),
            OpsWalletTransactionType::CASH,
            $reference,
            $actor,
            __('operational.wallet.adjustment_credit')
        );
    }

    public function adjustIncomeBalance(
        OpsWallet $wallet,
        float $oldAmount,
        float $newAmount,
        Model $reference,
        User $actor
    ): void {
        $difference = round($newAmount - $oldAmount, 2);

        if ($difference === 0.0) {
            return;
        }

        if ($difference > 0) {
            $this->credit(
                $wallet,
                $difference,
                OpsWalletTransactionType::CASH,
                $reference,
                $actor,
                __('operational.wallet.adjustment_credit')
            );

            return;
        }

        $this->debit(
            $wallet,
            abs($difference),
            OpsWalletTransactionType::CASH,
            $reference,
            $actor,
            __('operational.wallet.adjustment_debit')
        );
    }
}
