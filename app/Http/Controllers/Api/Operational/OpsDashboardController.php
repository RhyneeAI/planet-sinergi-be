<?php

namespace App\Http\Controllers\Api\Operational;

use App\Enums\OpsTransferConfirmationStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\OpsExpense;
use App\Models\OpsIncome;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class OpsDashboardController extends Controller
{
    use ScopesOperationalBySubCompany;

    public function adminDashboard(Request $request)
    {
        $dateFrom = $request->filled('date_from')
            ? Carbon::parse($request->date_from)->startOfDay()
            : today()->startOfDay();

        $dateTo = $request->filled('date_to')
            ? Carbon::parse($request->date_to)->endOfDay()
            : today()->endOfDay();

        if ($dateFrom->format('Y-m') !== $dateTo->format('Y-m')) {
            return response()->json([
                'success' => false,
                'message' => __('operational.dashboard.date_filter_must_be_same_month'),
                'code' => 422,
            ], 422);
        }

        $previousDateFrom = $dateFrom->copy()->subMonth();
        $previousDateTo = $dateTo->copy()->subMonth();

        $applySubCompanyScope = function ($query) use ($request) {
            $this->applySubCompanyFilter($query, $request);
        };

        $totalIncome = OpsIncome::whereBetween('date', [$dateFrom, $dateTo])
            ->tap($applySubCompanyScope)
            ->sum('amount');

        $totalExpense = OpsExpense::whereBetween('date', [$dateFrom, $dateTo])
            ->tap($applySubCompanyScope)
            ->sum('amount');

        $previousTotalExpense = OpsExpense::whereBetween('date', [$previousDateFrom, $previousDateTo])
            ->tap($applySubCompanyScope)
            ->sum('amount');

        $totalActiveMandor = User::where('role', Role::MANDOR)
            ->where('is_active', true)
            ->count();

        $waitingConfirmationIncome = OpsIncome::whereBetween('date', [$dateFrom, $dateTo])
            ->tap($applySubCompanyScope)
            ->whereHas('transferConfirmation', function ($query) {
                $query->where('status', OpsTransferConfirmationStatus::PENDING->value);
            })
            ->count();

        if ($previousTotalExpense == 0) {
            $expensePercentage = $totalExpense > 0 ? 100 : 0;
        } else {
            $expensePercentage = round(
                (($totalExpense - $previousTotalExpense) / $previousTotalExpense) * 100,
                2
            );
        }

        return response()->json([
            'success' => true,
            'message' => __('operational.dashboard.admin_summary'),
            'data' => [
                'total_income' => (float) $totalIncome,
                'total_expense' => (float) $totalExpense,
                'remaining_amount' => (float) $totalIncome - (float) $totalExpense,

                'previous_total_expense' => $previousTotalExpense,
                'expense_percentage' => abs($expensePercentage),
                'expense_trend' => match (true) {
                    $expensePercentage > 0 => 'up',
                    $expensePercentage < 0 => 'down',
                    default => 'stable',
                },

                'total_mandor_active' => $totalActiveMandor,
                'waiting_confirmation_income' => $waitingConfirmationIncome,
            ],
        ]);
    }

    public function mandorDashboard(Request $request)
    {
        $user = $request->user();

        $dateFrom = $request->filled('date_from')
            ? Carbon::parse($request->date_from)->startOfDay()
            : today()->startOfDay();

        $dateTo = $request->filled('date_to')
            ? Carbon::parse($request->date_to)->endOfDay()
            : today()->endOfDay();

        if ($dateFrom->format('Y-m') !== $dateTo->format('Y-m')) {
            return response()->json([
                'success' => false,
                'message' => __('operational.dashboard.date_filter_must_be_same_month'),
                'code' => 422,
            ], 422);
        }

        $previousDateFrom = $dateFrom->copy()->subMonth();
        $previousDateTo = $dateTo->copy()->subMonth();

        $totalIncome = OpsIncome::where('mandor_id', $user->id)
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->sum('amount');

        $totalExpense = OpsExpense::where('mandor_id', $user->id)
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->sum('amount');

        $previousTotalExpense = OpsExpense::where('mandor_id', $user->id)
            ->whereBetween('date', [$previousDateFrom, $previousDateTo])
            ->sum('amount');

        $pendingConfirmation = OpsIncome::where('mandor_id', $user->id)
            ->whereHas('transferConfirmation', function ($query) {
                $query->where('status', OpsTransferConfirmationStatus::PENDING->value);
            })
            ->count();

        if ($previousTotalExpense == 0) {
            $expensePercentage = $totalExpense > 0 ? 100 : 0;
        } else {
            $expensePercentage = round(
                (($totalExpense - $previousTotalExpense) / $previousTotalExpense) * 100,
                2
            );
        }

        return response()->json([
            'success' => true,
            'message' => __('operational.dashboard.mandor_summary'),
            'data' => [
                'total_income' => (float) $totalIncome,
                'total_expense' => (float) $totalExpense,
                'remaining_amount' => (float) $totalIncome - (float) $totalExpense,

                'previous_total_expense' => $previousTotalExpense,
                'expense_percentage' => abs($expensePercentage),
                'expense_trend' => match (true) {
                    $expensePercentage > 0 => 'up',
                    $expensePercentage < 0 => 'down',
                    default => 'stable',
                },

                'pending_confirmation_count' => $pendingConfirmation,
            ],
        ]);
    }
}
