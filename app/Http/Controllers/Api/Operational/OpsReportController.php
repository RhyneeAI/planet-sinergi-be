<?php

namespace App\Http\Controllers\Api\Operational;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operational\OpsReportRequest;
use App\Models\OpsExpense;
use App\Models\OpsIncome;
use App\Models\User;
use Carbon\Carbon;

class OpsReportController extends Controller
{
    public function incomeExpenseReport(OpsReportRequest $request)
    {
        $companyId = $request->user()->company_id;
        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate   = Carbon::parse($request->end_date)->endOfDay();

        $saldoAwalIncome = OpsIncome::whereDate('date', '<', $startDate)->sum('amount');
        $saldoAwalExpense = OpsExpense::whereDate('date', '<', $startDate)->sum('amount');
        $saldoAwal = (float) $saldoAwalIncome - (float) $saldoAwalExpense;

        $saldoAkhirIncome = OpsIncome::whereDate('date', '<=', $endDate)->sum('amount');
        $saldoAkhirExpense = OpsExpense::whereDate('date', '<=', $endDate)->sum('amount');
        $saldoAkhir = (float) $saldoAkhirIncome - (float) $saldoAkhirExpense;

        $mandors = User::where('company_id', $companyId)
            ->where('role', Role::MANDOR)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $groups = collect();

        foreach ($mandors as $mandor) {
            $incomes = OpsIncome::where('mandor_id', $mandor->id)
                ->whereDate('date', '>=', $startDate)
                ->whereDate('date', '<=', $endDate)
                ->with('subCompany:id,uuid,name')
                ->orderBy('date')
                ->orderBy('created_at')
                ->get()
                ->map(fn ($income) => [
                    'uuid'          => $income->uuid,
                    'name'          => $income->name,
                    'amount'        => (float) $income->amount,
                    'date'          => $income->date->format('Y-m-d'),
                    'payment_method'=> $income->payment_method->value,
                    'source_type'   => $income->source_type->value,
                    'sub_company'   => $income->subCompany ? [
                        'uuid' => $income->subCompany->uuid,
                        'name' => $income->subCompany->name,
                    ] : null,
                    'note'          => $income->note,
                ]);

            $expenses = OpsExpense::where('mandor_id', $mandor->id)
                ->whereDate('date', '>=', $startDate)
                ->whereDate('date', '<=', $endDate)
                ->with('subCompany:id,uuid,name')
                ->orderBy('date')
                ->orderBy('created_at')
                ->get()
                ->map(fn ($expense) => [
                    'uuid'          => $expense->uuid,
                    'name'          => $expense->name,
                    'amount'        => (float) $expense->amount,
                    'date'          => $expense->date->format('Y-m-d'),
                    'payment_method'=> $expense->payment_method->value,
                    'expense_type'  => $expense->expense_type->value,
                    'sub_company'   => $expense->subCompany ? [
                        'uuid' => $expense->subCompany->uuid,
                        'name' => $expense->subCompany->name,
                    ] : null,
                    'note'          => $expense->note,
                ]);

            if ($incomes->isNotEmpty() || $expenses->isNotEmpty()) {
                $totalIncome  = (float) $incomes->sum('amount');
                $totalExpense = (float) $expenses->sum('amount');

                $groups->push([
                    'mandor' => [
                        'uuid' => $mandor->uuid,
                        'name' => $mandor->name,
                    ],
                    'total_income'  => $totalIncome,
                    'total_expense' => $totalExpense,
                    'remaining'     => $totalIncome - $totalExpense,
                    'incomes'       => $incomes,
                    'expenses'      => $expenses,
                ]);
            }
        }

        $internalIncomes = OpsIncome::whereNull('mandor_id')
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate)
            ->orderBy('date')
            ->orderBy('created_at')
            ->get()
            ->map(fn ($income) => [
                'uuid'          => $income->uuid,
                'name'          => $income->name,
                'amount'        => (float) $income->amount,
                'date'          => $income->date->format('Y-m-d'),
                'payment_method'=> $income->payment_method->value,
                'source_type'   => $income->source_type->value,
                'sub_company'   => null,
                'note'          => $income->note,
            ]);

        $internalExpenses = OpsExpense::whereNull('mandor_id')
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate)
            ->orderBy('date')
            ->orderBy('created_at')
            ->get()
            ->map(fn ($expense) => [
                'uuid'          => $expense->uuid,
                'name'          => $expense->name,
                'amount'        => (float) $expense->amount,
                'date'          => $expense->date->format('Y-m-d'),
                'payment_method'=> $expense->payment_method->value,
                'expense_type'  => $expense->expense_type->value,
                'sub_company'   => null,
                'note'          => $expense->note,
            ]);

        if ($internalIncomes->isNotEmpty() || $internalExpenses->isNotEmpty()) {
            $totalInternalIncome  = (float) $internalIncomes->sum('amount');
            $totalInternalExpense = (float) $internalExpenses->sum('amount');

            $groups->push([
                'mandor'        => null,
                'total_income'  => $totalInternalIncome,
                'total_expense' => $totalInternalExpense,
                'remaining'     => $totalInternalIncome - $totalInternalExpense,
                'incomes'       => $internalIncomes,
                'expenses'      => $internalExpenses,
            ]);
        }

        $totalPeriodIncome  = $groups->sum('total_income');
        $totalPeriodExpense = $groups->sum('total_expense');

        return response()->json([
            'success' => true,
            'message' => __('operational.report.income_expense_report'),
            'data'    => [
                'period' => [
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date'   => $endDate->format('Y-m-d'),
                ],
                'saldo_awal'         => $saldoAwal,
                'saldo_akhir'        => $saldoAkhir,
                'total_income'       => $totalPeriodIncome,
                'total_expense'      => $totalPeriodExpense,
                'total_remaining'    => $totalPeriodIncome - $totalPeriodExpense,
                'groups'             => $groups->values(),
            ],
        ]);
    }
}
