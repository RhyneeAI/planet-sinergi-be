<?php

namespace App\Http\Controllers\Api\Operational;

use App\Enums\OpsExpenseType;
use App\Enums\Role;
use App\Exports\OpsIncomeExpenseExport;
use App\Helpers\FileHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operational\OpsReportRequest;
use App\Models\OpsExpense;
use App\Models\OpsIncome;
use App\Models\User;
use App\Services\ExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class OpsReportController extends Controller
{
    public function __construct(
        protected ExportService $exportService,
    ) {}

    public function incomeExpenseReport(OpsReportRequest $request)
    {
        $data = $this->buildReportData($request);

        return response()->json([
            'success' => true,
            'message' => __('operational.report.income_expense_report'),
            'data'    => $data,
        ]);
    }

    public function downloadIncomeExpenseReport(OpsReportRequest $request)
    {
        $data = $this->buildReportData($request);

        if ($data['groups']->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => __('operational.report.no_data'),
                'code'    => 404,
            ], 404);
        }

        $type  = $request->download_type;
        $types = $type ? [$type] : ['PDF', 'EXCEL'];

        $response = [
            'success' => true,
            'message' => __('operational.report.download_ready'),
            'data'    => [
                'period' => $data['period'],
            ],
        ];

        if (in_array('PDF', $types)) {
            $response['data']['pdf_download_url'] = $this->generatePdf($request, $data);
        }

        if (in_array('EXCEL', $types)) {
            $response['data']['xlsx_download_url'] = $this->generateXlsx($request, $data);
        }

        return response()->json($response);
    }

    protected function buildReportData(OpsReportRequest $request): array
    {
        $companyId  = $request->user()->company_id;
        $startDate  = Carbon::parse($request->start_date)->startOfDay();
        $endDate    = Carbon::parse($request->end_date)->endOfDay();
        $mandorUuid = $request->mandor_uuid;

        $incomeQuery   = OpsIncome::query();
        $expenseQuery  = OpsExpense::query();

        if ($mandorUuid) {
            $mandorId = User::where('uuid', $mandorUuid)->where('company_id', $companyId)->value('id');
            $incomeQuery->where('mandor_id', $mandorId);
            $expenseQuery->where('mandor_id', $mandorId)->where('expense_type', '!=', OpsExpenseType::MANDOR);
        }

        $saldoAwalIncome  = (clone $incomeQuery)->whereDate('date', '<', $startDate)->sum('amount');
        $saldoAwalExpense = (clone $expenseQuery)->whereDate('date', '<', $startDate)->sum('amount');
        $saldoAwal        = (float) $saldoAwalIncome - (float) $saldoAwalExpense;

        $saldoAkhirIncome  = (clone $incomeQuery)->whereDate('date', '<=', $endDate)->sum('amount');
        $saldoAkhirExpense = (clone $expenseQuery)->whereDate('date', '<=', $endDate)->sum('amount');
        $saldoAkhir        = (float) $saldoAkhirIncome - (float) $saldoAkhirExpense;

        $mandors = User::where('company_id', $companyId)
            ->where('role', Role::MANDOR)
            ->orderBy('name')
            ->when($mandorUuid, fn($q) => $q->where('uuid', $mandorUuid))
            ->get();

        $groups = collect();

        // Pusat (internal) ditempatkan paling awal
        if (!$mandorUuid) {
            $internalIncomes  = $this->internalIncomes($startDate, $endDate);
            $internalExpenses = $this->internalExpenses($startDate, $endDate);

            $internalSaldoAwalIncome  = OpsIncome::whereNull('mandor_id')->whereDate('date', '<', $startDate)->sum('amount');
            $internalSaldoAwalExpense = OpsExpense::where(function ($q) {
                    $q->whereNull('mandor_id')
                      ->orWhere('expense_type', OpsExpenseType::MANDOR);
                })
                ->whereDate('date', '<', $startDate)->sum('amount');

            $internalSaldoAkhirIncome  = OpsIncome::whereNull('mandor_id')->whereDate('date', '<=', $endDate)->sum('amount');
            $internalSaldoAkhirExpense = OpsExpense::where(function ($q) {
                    $q->whereNull('mandor_id')
                      ->orWhere('expense_type', OpsExpenseType::MANDOR);
                })
                ->whereDate('date', '<=', $endDate)->sum('amount');

            $hasInternalData = $internalIncomes->isNotEmpty()
                || $internalExpenses->isNotEmpty()
                || $internalSaldoAwalIncome > 0
                || $internalSaldoAwalExpense > 0;

            if ($hasInternalData) {
                $totalInternalIncome  = (float) $internalIncomes->sum('amount');
                $totalInternalExpense = (float) $internalExpenses->sum('amount');

                $groups->push([
                    'mandor'        => null,
                    'sub_companies' => collect(),
                    'saldo_awal'    => (float) $internalSaldoAwalIncome - (float) $internalSaldoAwalExpense,
                    'saldo_akhir'   => (float) $internalSaldoAkhirIncome - (float) $internalSaldoAkhirExpense,
                    'total_income'  => $totalInternalIncome,
                    'total_expense' => $totalInternalExpense,
                    'remaining'     => $totalInternalIncome - $totalInternalExpense,
                    'incomes'       => $internalIncomes,
                    'expenses'      => $internalExpenses,
                ]);
            }
        }

        foreach ($mandors as $mandor) {
            $incomes = $this->mandorIncomes($mandor->id, $startDate, $endDate);
            $expenses = $this->mandorExpenses($mandor->id, $startDate, $endDate);

            $mandorSaldoAwalIncome  = OpsIncome::where('mandor_id', $mandor->id)->whereDate('date', '<', $startDate)->sum('amount');
            $mandorSaldoAwalExpense = OpsExpense::where('mandor_id', $mandor->id)
                ->where('expense_type', '!=', OpsExpenseType::MANDOR)
                ->whereDate('date', '<', $startDate)->sum('amount');

            $mandorSaldoAkhirIncome  = OpsIncome::where('mandor_id', $mandor->id)->whereDate('date', '<=', $endDate)->sum('amount');
            $mandorSaldoAkhirExpense = OpsExpense::where('mandor_id', $mandor->id)
                ->where('expense_type', '!=', OpsExpenseType::MANDOR)
                ->whereDate('date', '<=', $endDate)->sum('amount');

            $mandorSubCompanies = $mandor->subCompanies()
                ->get(['uuid', 'name', 'code'])
                ->map(fn ($sc) => [
                    'uuid' => $sc->uuid,
                    'name' => $sc->name,
                    'code' => $sc->code,
                ]);

            $hasMandorData = $incomes->isNotEmpty()
                || $expenses->isNotEmpty()
                || $mandorSaldoAwalIncome > 0
                || $mandorSaldoAwalExpense > 0;

            if ($hasMandorData) {
                $totalIncome  = (float) $incomes->sum('amount');
                $totalExpense = (float) $expenses->sum('amount');

                $groups->push([
                    'mandor' => [
                        'uuid' => $mandor->uuid,
                        'name' => $mandor->name,
                    ],
                    'sub_companies' => $mandorSubCompanies,
                    'saldo_awal'    => (float) $mandorSaldoAwalIncome - (float) $mandorSaldoAwalExpense,
                    'saldo_akhir'   => (float) $mandorSaldoAkhirIncome - (float) $mandorSaldoAkhirExpense,
                    'total_income'  => $totalIncome,
                    'total_expense' => $totalExpense,
                    'remaining'     => $totalIncome - $totalExpense,
                    'incomes'       => $incomes,
                    'expenses'      => $expenses,
                ]);
            }
        }

        $totalPeriodIncome  = $groups->sum('total_income');
        $totalPeriodExpense = $groups->sum('total_expense');

        return [
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date'   => $endDate->format('Y-m-d'),
            ],
            'saldo_awal'      => $saldoAwal,
            'saldo_akhir'     => $saldoAkhir,
            'total_income'    => $totalPeriodIncome,
            'total_expense'   => $totalPeriodExpense,
            'total_remaining' => $totalPeriodIncome - $totalPeriodExpense,
            'groups'          => $groups->values(),
        ];
    }

    protected function mandorIncomes(int $mandorId, Carbon $startDate, Carbon $endDate): Collection
    {
        return OpsIncome::where('mandor_id', $mandorId)
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate)
            ->orderBy('date')
            ->orderBy('created_at')
            ->get()
            ->map(fn ($income) => [
                'uuid'           => $income->uuid,
                'name'           => $income->name,
                'amount'         => (float) $income->amount,
                'date'           => $income->date->format('Y-m-d'),
                'payment_method' => $income->payment_method->value,
                'source_type'    => $income->source_type->value,
                'note'           => $income->note,
            ]);
    }

    protected function mandorExpenses(int $mandorId, Carbon $startDate, Carbon $endDate): Collection
    {
        return OpsExpense::where('mandor_id', $mandorId)
            ->where('expense_type', '!=', OpsExpenseType::MANDOR)
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate)
            ->orderBy('date')
            ->orderBy('created_at')
            ->get()
            ->map(fn ($expense) => [
                'uuid'           => $expense->uuid,
                'name'           => $expense->name,
                'amount'         => (float) $expense->amount,
                'date'           => $expense->date->format('Y-m-d'),
                'payment_method' => $expense->payment_method->value,
                'expense_type'   => $expense->expense_type->value,
                'note'           => $expense->note,
            ]);
    }

    protected function internalIncomes(Carbon $startDate, Carbon $endDate): Collection
    {
        return OpsIncome::whereNull('mandor_id')
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate)
            ->orderBy('date')
            ->orderBy('created_at')
            ->get()
            ->map(fn ($income) => [
                'uuid'           => $income->uuid,
                'name'           => $income->name,
                'amount'         => (float) $income->amount,
                'date'           => $income->date->format('Y-m-d'),
                'payment_method' => $income->payment_method->value,
                'source_type'    => $income->source_type->value,
                'note'           => $income->note,
            ]);
    }

    protected function internalExpenses(Carbon $startDate, Carbon $endDate): Collection
    {
        return OpsExpense::with(['mandor', 'subCompany'])
            ->where(function ($q) {
                $q->whereNull('mandor_id')
                  ->orWhere('expense_type', OpsExpenseType::MANDOR);
            })
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate)
            ->orderBy('date')
            ->orderBy('created_at')
            ->get()
            ->map(fn ($expense) => [
                'uuid'           => $expense->uuid,
                'name'           => $expense->name,
                'amount'         => (float) $expense->amount,
                'date'           => $expense->date->format('Y-m-d'),
                'payment_method' => $expense->payment_method->value,
                'expense_type'   => $expense->expense_type->value,
                'note'           => $expense->note,
                'mandor'         => $expense->mandor ? [
                    'uuid' => $expense->mandor->uuid,
                    'name' => $expense->mandor->name,
                ] : null,
                'sub_company'    => $expense->subCompany ? [
                    'uuid' => $expense->subCompany->uuid,
                    'name' => $expense->subCompany->name,
                    'code' => $expense->subCompany->code,
                ] : null,
            ]);
    }

    protected function generatePdf($request, array $data): string
    {
        $cached = $this->exportService->resolveCache(
            $request,
            'income-expense-pdf',
            $request->all(),
            'pdf',
            'operational'
        );
        if ($cached) {
            return $cached['download_url'];
        }

        $filename = 'income-expense-' . now()->format('YmdHis') . '.pdf';
        $storagePath = 'reports/operational/' . $filename;

        $pdf = Pdf::loadView('reports.operational.income-expense', [
            'period'         => $data['period'],
            'saldo_awal'     => $data['saldo_awal'],
            'saldo_akhir'    => $data['saldo_akhir'],
            'groups'         => $data['groups'],
            'total_income'   => $data['total_income'],
            'total_expense'  => $data['total_expense'],
            'total_remaining'=> $data['total_remaining'],
        ])->setPaper('a4', 'landscape');

        FileHelper::saveFile($storagePath, $pdf->output());

        $this->exportService->saveCacheAlias($request, 'income-expense-pdf', $request->all(), 'pdf', 'operational', $storagePath);

        return FileHelper::downloadUrl($storagePath);
    }

    protected function generateXlsx($request, array $data): string
    {
        $cached = $this->exportService->resolveCache(
            $request,
            'income-expense-xlsx',
            $request->all(),
            'xlsx',
            'operational'
        );
        if ($cached) {
            return $cached['download_url'];
        }

        $filename = 'income-expense-' . now()->format('YmdHis') . '.xlsx';
        $storagePath = 'reports/operational/' . $filename;

        FileHelper::saveExcel(new OpsIncomeExpenseExport($data, 'Laporan Operasional'), $storagePath);

        $this->exportService->saveCacheAlias($request, 'income-expense-xlsx', $request->all(), 'xlsx', 'operational', $storagePath);

        return FileHelper::downloadUrl($storagePath);
    }
}
