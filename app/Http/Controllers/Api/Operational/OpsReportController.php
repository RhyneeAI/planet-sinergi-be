<?php

namespace App\Http\Controllers\Api\Operational;

use App\Enums\OpsExpenseType;
use App\Enums\OpsPaymentMethod;
use App\Enums\Role;
use App\Exports\OpsIncomeExpenseExport;
use App\Helpers\FileHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operational\OpsReportRequest;
use App\Http\Traits\DataTablesResponse;
use App\Models\OpsExpense;
use App\Models\OpsIncome;
use App\Models\User;
use App\Services\ExportService;
use App\Services\Operational\OpsFileService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class OpsReportController extends Controller
{
    use DataTablesResponse;
    public function __construct(
        protected ExportService $exportService,
        protected OpsFileService $fileService,
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
        $user         = $request->user();
        $mandorUuid   = $request->mandor_uuid;
        $isKepala     = $user->role === Role::KEPALA_MANDOR;

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

        $saldoAwalMethods   = $this->paymentMethodSaldo($incomeQuery, $expenseQuery, '<', $startDate);
        $saldoAkhirMethods  = $this->paymentMethodSaldo($incomeQuery, $expenseQuery, '<=', $endDate);

        $mandorRoles = [Role::MANDOR, Role::KEPALA_MANDOR];

        $mandors = User::where('company_id', $companyId)
            ->whereIn('role', $mandorRoles)
            ->orderBy('name')
            ->when($mandorUuid, fn($q) => $q->where('uuid', $mandorUuid))
            ->get()
            ->when($isKepala && !$mandorUuid, fn ($collection) => $collection->sortByDesc(fn ($m) => $m->id === $user->id));

        $groups = collect();
        $showInternal = !$isKepala && !$mandorUuid;

        // Pusat (internal) — skip untuk KEPALA_MANDOR
        if ($showInternal) {
            $internalIncomes  = $this->internalIncomes($startDate, $endDate);
            $internalExpenses = $this->internalExpenses($startDate, $endDate);

            $internalIncomeBase  = OpsIncome::whereNull('mandor_id');
            $internalExpenseBase = OpsExpense::where(function ($q) {
                    $q->whereNull('mandor_id')
                      ->orWhere('expense_type', OpsExpenseType::MANDOR);
                });

            $internalSaldoAwalIncome  = (clone $internalIncomeBase)->whereDate('date', '<', $startDate)->sum('amount');
            $internalSaldoAwalExpense = (clone $internalExpenseBase)->whereDate('date', '<', $startDate)->sum('amount');

            $internalSaldoAkhirIncome  = (clone $internalIncomeBase)->whereDate('date', '<=', $endDate)->sum('amount');
            $internalSaldoAkhirExpense = (clone $internalExpenseBase)->whereDate('date', '<=', $endDate)->sum('amount');

            $internalSaldoAwalMethods  = $this->paymentMethodSaldo($internalIncomeBase, $internalExpenseBase, '<', $startDate);
            $internalSaldoAkhirMethods = $this->paymentMethodSaldo($internalIncomeBase, $internalExpenseBase, '<=', $endDate);

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
                    'saldo_awal_qris'       => $internalSaldoAwalMethods['qris'],
                    'saldo_awal_tunai'      => $internalSaldoAwalMethods['tunai'],
                    'saldo_awal_split_bill' => $internalSaldoAwalMethods['split_bill'],
                    'saldo_akhir_qris'      => $internalSaldoAkhirMethods['qris'],
                    'saldo_akhir_tunai'     => $internalSaldoAkhirMethods['tunai'],
                    'saldo_akhir_split_bill'=> $internalSaldoAkhirMethods['split_bill'],
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

            $mandorIncomeBase  = OpsIncome::where('mandor_id', $mandor->id);
            $mandorExpenseBase = OpsExpense::where('mandor_id', $mandor->id)
                ->where('expense_type', '!=', OpsExpenseType::MANDOR);

            $mandorSaldoAwalIncome  = (clone $mandorIncomeBase)->whereDate('date', '<', $startDate)->sum('amount');
            $mandorSaldoAwalExpense = (clone $mandorExpenseBase)->whereDate('date', '<', $startDate)->sum('amount');

            $mandorSaldoAkhirIncome  = (clone $mandorIncomeBase)->whereDate('date', '<=', $endDate)->sum('amount');
            $mandorSaldoAkhirExpense = (clone $mandorExpenseBase)->whereDate('date', '<=', $endDate)->sum('amount');

            $mandorSaldoAwalMethods  = $this->paymentMethodSaldo($mandorIncomeBase, $mandorExpenseBase, '<', $startDate);
            $mandorSaldoAkhirMethods = $this->paymentMethodSaldo($mandorIncomeBase, $mandorExpenseBase, '<=', $endDate);

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
                || $mandorSaldoAwalExpense > 0
                || $mandorSubCompanies->isNotEmpty();

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
                    'saldo_awal_qris'       => $mandorSaldoAwalMethods['qris'],
                    'saldo_awal_tunai'      => $mandorSaldoAwalMethods['tunai'],
                    'saldo_awal_split_bill' => $mandorSaldoAwalMethods['split_bill'],
                    'saldo_akhir_qris'      => $mandorSaldoAkhirMethods['qris'],
                    'saldo_akhir_tunai'     => $mandorSaldoAkhirMethods['tunai'],
                    'saldo_akhir_split_bill'=> $mandorSaldoAkhirMethods['split_bill'],
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
            'saldo_awal'            => $saldoAwal,
            'saldo_akhir'           => $saldoAkhir,
            'saldo_awal_qris'       => $saldoAwalMethods['qris'],
            'saldo_awal_tunai'      => $saldoAwalMethods['tunai'],
            'saldo_awal_split_bill' => $saldoAwalMethods['split_bill'],
            'saldo_akhir_qris'      => $saldoAkhirMethods['qris'],
            'saldo_akhir_tunai'     => $saldoAkhirMethods['tunai'],
            'saldo_akhir_split_bill'=> $saldoAkhirMethods['split_bill'],
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
                'proof_files'    => $this->mapProofFiles($income->proof_files ?? []),
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
                'proof_files'    => $this->mapProofFiles($expense->proof_files ?? []),
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
                'proof_files'    => $this->mapProofFiles($income->proof_files ?? []),
            ]);
    }

    protected function paymentMethodSaldo(mixed $incomeQuery, mixed $expenseQuery, string $dateOp, Carbon $dateValue): array
    {
        $methods = [
            'qris'       => OpsPaymentMethod::TRANSFER,
            'tunai'      => OpsPaymentMethod::CASH,
            'split_bill' => OpsPaymentMethod::SPLIT_BILL,
        ];

        $result = [];
        foreach ($methods as $key => $enum) {
            $inc = (clone $incomeQuery)->where('payment_method', $enum)->whereDate('date', $dateOp, $dateValue)->sum('amount');
            $exp = (clone $expenseQuery)->where('payment_method', $enum)->whereDate('date', $dateOp, $dateValue)->sum('amount');
            $result[$key] = (float) $inc - (float) $exp;
        }

        return $result;
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
                'proof_files'    => $this->mapProofFiles($expense->proof_files ?? []),
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

    protected function mapProofFiles(array $paths): array
    {
        return $this->fileService->urls($paths);
    }

    public function incomeExpenseDetail(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'mandor_uuid' => 'nullable|string|uuid',
            'per_page'    => 'nullable|integer|min:1|max:100',
        ]);

        $companyId = $request->user()->company_id;
        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate   = Carbon::parse($request->end_date)->endOfDay();
        $perPage   = $request->input('per_page', 15);

        $user       = $request->user();
        $isKepala   = $user->role === Role::KEPALA_MANDOR;
        $incomes    = OpsIncome::query();
        $expenses   = OpsExpense::query();

        if ($request->filled('mandor_uuid')) {
            $mandorId = User::where('uuid', $request->mandor_uuid)
                ->where('company_id', $companyId)
                ->value('id');

            $incomes->where('mandor_id', $mandorId);
            $expenses->where('mandor_id', $mandorId)->where('expense_type', '!=', OpsExpenseType::MANDOR);
        } elseif ($isKepala) {
            $incomes->whereNotNull('mandor_id');
            $expenses->whereNotNull('mandor_id')->where('expense_type', '!=', OpsExpenseType::MANDOR);
        } else {
            $incomes->whereNull('mandor_id');
            $expenses->where(function (Builder $q) {
                $q->whereNull('mandor_id')
                  ->orWhere('expense_type', OpsExpenseType::MANDOR);
            });
        }

        $incomes->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate)
            ->orderBy('date')
            ->orderBy('created_at');

        $expenses->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate)
            ->orderBy('date')
            ->orderBy('created_at');

        $incomeResults = $incomes->get()->map(fn ($income) => [
            'type'           => 'income',
            'uuid'           => $income->uuid,
            'name'           => $income->name,
            'amount'         => (float) $income->amount,
            'date'           => $income->date->format('Y-m-d'),
            'payment_method' => $income->payment_method->value,
            'source_type'    => $income->source_type->value,
            'note'           => $income->note,
            'proof_files'    => $this->mapProofFiles($income->proof_files ?? []),
        ]);

        $expenseResults = $expenses->get()->map(fn ($expense) => [
            'type'           => 'expense',
            'uuid'           => $expense->uuid,
            'name'           => $expense->name,
            'amount'         => (float) $expense->amount,
            'date'           => $expense->date->format('Y-m-d'),
            'payment_method' => $expense->payment_method->value,
            'expense_type'   => $expense->expense_type->value,
            'note'           => $expense->note,
            'proof_files'    => $this->mapProofFiles($expense->proof_files ?? []),
        ]);

        $merged = $incomeResults->concat($expenseResults)
            ->sortBy('date')
            ->values();

        $page  = (int) $request->integer('page', 1);
        $total = $merged->count();
        $items = $merged->forPage($page, $perPage)->values();

        $paginator = new LengthAwarePaginator(
            $items,
            $total,
            (int) $perPage,
            $page,
        );

        return response()->json(
            $this->dataTablesResponse($request, $paginator, [
                'success' => true,
                'message' => __('operational.report.income_expense_detail'),
                'data' => $items,
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page'     => $paginator->perPage(),
                    'total'        => $paginator->total(),
                    'last_page'    => $paginator->lastPage(),
                ],
            ])
        );
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
