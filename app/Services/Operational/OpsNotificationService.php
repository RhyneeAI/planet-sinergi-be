<?php

namespace App\Services\Operational;

use App\Enums\OpsNotificationType;
use App\Enums\Role;
use App\Models\OpsExpense;
use App\Models\OpsIncome;
use App\Models\OpsNotification;
use App\Models\OpsTransferConfirmation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class OpsNotificationService
{
    public function notifyMandorIncomePending(
        User $mandor,
        OpsIncome $income,
        OpsTransferConfirmation $confirmation
    ): OpsNotification {
        return OpsNotification::create([
            'user_id' => $mandor->id,
            'type' => OpsNotificationType::INCOME_PENDING,
            'title' => __('operational.notifications.income_pending.title'),
            'message' => __('operational.notifications.income_pending.message'),
            'notifiable_type' => 'ops_transfer_confirmations',
            'notifiable_id' => $confirmation->id,
            'company_id' => $mandor->company_id,
        ]);
    }

    public function notifyAdminsInsufficientBalance(User $mandor, float $amount, string $name): void
    {
        $admins = User::withoutGlobalScopes()
            ->where('company_id', $mandor->company_id)
            ->whereIn('role', [Role::SUPERADMIN, Role::OWNER, Role::ADMIN])
            ->where('is_active', true)
            ->get();

        foreach ($admins as $admin) {
            OpsNotification::create([
                'user_id' => $admin->id,
                'type' => OpsNotificationType::EXPENSE_INSUFFICIENT_BALANCE,
                'title' => __('operational.notifications.insufficient_balance.title'),
                'message' => __('operational.notifications.insufficient_balance.message', [
                    'mandor' => $mandor->name,
                    'amount' => number_format($amount, 0, ',', '.'),
                ]) . ' (' . $name . ')',
                'notifiable_type' => null,
                'notifiable_id' => null,
                'company_id' => $mandor->company_id,
            ]);
        }
    }

    public function notifyAdminsExpenseCreated(User $mandor, OpsExpense $expense): void
    {
        $admins = User::withoutGlobalScopes()
            ->where('company_id', $mandor->company_id)
            ->whereIn('role', [Role::SUPERADMIN, Role::OWNER, Role::ADMIN])
            ->where('is_active', true)
            ->get();

        foreach ($admins as $admin) {
            OpsNotification::create([
                'user_id' => $admin->id,
                'type' => OpsNotificationType::EXPENSE_CREATED,
                'title' => __('operational.notifications.expense_created.title'),
                'message' => __('operational.notifications.expense_created.message', [
                    'mandor' => $mandor->name,
                    'name' => $expense->name,
                    'amount' => number_format((float) $expense->amount, 0, ',', '.'),
                ]),
                'notifiable_type' => 'ops_expenses',
                'notifiable_id' => $expense->id,
                'company_id' => $mandor->company_id,
            ]);
        }
    }

    public function markAsRead(OpsNotification $notification): OpsNotification
    {
        if (!$notification->is_read) {
            $notification->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }

        return $notification->fresh();
    }

    public function markAllAsRead(User $user): int
    {
        return OpsNotification::where('user_id', $user->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }
}
