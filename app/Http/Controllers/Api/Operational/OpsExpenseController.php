<?php

namespace App\Http\Controllers\Api\Operational;

use App\Enums\OpsExpenseType;
use App\Enums\OpsTransferConfirmationStatus;
use App\Enums\OpsWalletTransactionType;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operational\OpsExpenseRequest;
use App\Http\Resources\Operational\OpsExpenseResource;
use App\Models\OpsEditLog;
use App\Models\OpsExpense;
use App\Models\OpsTransferConfirmation;
use App\Services\Operational\OpsFileService;
use App\Services\Operational\OpsNotificationService;
use App\Services\Operational\OpsOperationalConfigService;
use App\Services\Operational\OpsWalletService;
use App\Services\SubCompanyService;
use App\Http\Traits\DataTablesResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OpsExpenseController extends Controller
{
    use DataTablesResponse;
    use ReturnsEmptyShowResponse;
    use ScopesOperationalBySubCompany;
    use HandlesOperationalProofFiles;
    use UsesOperationalTransactionWindow;

    protected array $sortableColumns = ['name', 'date', 'amount', 'expense_type'];

    public function __construct(
        protected OpsFileService $fileService,
        protected OpsWalletService $walletService,
        protected OpsNotificationService $notificationService,
        protected SubCompanyService $subCompanyService,
        protected OpsOperationalConfigService $operationalConfig,
    ) {}

    public function index(Request $request)
    {
        $orderByKey = in_array($request->input('order_by_key', 'date'), $this->sortableColumns)
            ? $request->input('order_by_key', 'date')
            : 'date';
        $orderByValue = strtoupper($request->input('order_by_value', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $expenses = OpsExpense::with(['mandor', 'subCompany', 'createdBy', 'transferIncome.transferConfirmation', 'editLogs'])
            ->withCount('editLogs')
            ->when(true, fn (Builder $query) => $this->applySubCompanyFilter($query, $request))
            ->when($request->date_from, fn($q, $date) => $q->whereDate('date', '>=', $date))
            ->when($request->date_to, fn($q, $date) => $q->whereDate('date', '<=', $date))
            ->when(
                $request->mandor_uuid,
                fn($q, $uuid) =>
                $q->whereHas('mandor', fn($m) => $m->where('uuid', $uuid))
            )
            ->when($request->expense_type, fn ($q, $type) => $q->where('expense_type', $type))
            ->when($request->search, function ($query, $search) {
                $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%']);
            })
            ->orderBy($orderByKey, $orderByValue)
            ->paginate($request->input('per_page', 15));

        return response()->json(
            $this->dataTablesResponse($request, $expenses, [
                'success' => true,
                'message' => __('operational.expenses.list'),
                'data' => OpsExpenseResource::collection($expenses),
            ])
        );
    }

    public function pusat(Request $request)
    {
        $orderByKey = in_array($request->input('order_by_key', 'date'), $this->sortableColumns)
            ? $request->input('order_by_key', 'date')
            : 'date';
        $orderByValue = strtoupper($request->input('order_by_value', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $expenses = OpsExpense::with(['createdBy'])
            ->where(function ($q) {
                $q->where('expense_type', OpsExpenseType::INTERNAL)->whereNull('mandor_id')
                  ->orWhere('expense_type', OpsExpenseType::MANDOR);
            })
            ->when($request->date_from, fn($q, $date) => $q->whereDate('date', '>=', $date))
            ->when($request->date_to, fn($q, $date) => $q->whereDate('date', '<=', $date))
            ->when($request->search, function ($query, $search) {
                $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%']);
            })
            ->orderBy($orderByKey, $orderByValue)
            ->paginate($request->input('per_page', 15));

        return response()->json(
            $this->dataTablesResponse($request, $expenses, [
                'success' => true,
                'message' => __('operational.expenses.list'),
                'data' => OpsExpenseResource::collection($expenses),
            ])
        );
    }

    public function store(OpsExpenseRequest $request)
    {
        if ($response = $this->validateOperationalStoreDate('expense', $request->date)) {
            return $response;
        }

        if (in_array($request->user()->role, [Role::MANDOR, Role::KEPALA_MANDOR])) {
            return $this->storeAsMandor($request);
        }

        return $this->storeAsAdmin($request);
    }

    public function show(Request $request, string $uuid)
    {
        $opsExpense = OpsExpense::where('uuid', $uuid)->first();

        if (!$opsExpense) {
            return $this->emptyShowResponse(__('operational.expenses.detail'));
        }

        if (in_array($request->user()->role, [Role::MANDOR, Role::KEPALA_MANDOR])) {
            $this->authorizeExpenseAccess($opsExpense);
        }

        return response()->json([
            'success' => true,
            'message' => __('operational.expenses.detail'),
            'data' => new OpsExpenseResource(
                $opsExpense->load(['mandor', 'subCompany', 'createdBy', 'transferIncome.transferConfirmation', 'editLogs'])
            ),
        ]);
    }

    public function update(OpsExpenseRequest $request, string $uuid)
    {
        $opsExpense = OpsExpense::where('uuid', $uuid)->first();

        if (!$opsExpense) {
            return response()->json([
                'success' => false,
                'message' => __('operational.expenses.not_found'),
                'code' => 404,
            ], 404);
        }

        if ($response = $this->validateOperationalEditWindow('expense', $opsExpense)) {
            return $response;
        }

        if ($response = $this->validateOperationalStoreDate('expense', $request->date)) {
            return $response;
        }

        if (in_array($request->user()->role, [Role::MANDOR, Role::KEPALA_MANDOR])) {
            return $this->updateAsMandor($request, $opsExpense);
        }

        return $this->updateAsAdmin($request, $opsExpense);
    }

    public function destroy(Request $request, string $uuid)
    {
        $opsExpense = OpsExpense::where('uuid', $uuid)->first();

        if (!$opsExpense) {
            return response()->json([
                'success' => false,
                'message' => __('operational.expenses.not_found'),
                'code' => 404,
            ], 404);
        }

        if (in_array($request->user()->role, [Role::MANDOR, Role::KEPALA_MANDOR])) {
            return $this->destroyAsMandor($opsExpense);
        }

        return $this->destroyAsAdmin($opsExpense);
    }

    protected function storeAsAdmin(OpsExpenseRequest $request)
    {
        $companyId = $request->user()->company_id;
        $expenseType = OpsExpenseType::from($request->expense_type);
        $proofFiles = $this->storeProofFilesFromRequest($request, 'expense', 'admin');

        if ($expenseType === OpsExpenseType::INTERNAL) {
            $expense = OpsExpense::create([
                'name' => $request->name,
                'amount' => $request->amount,
                'date' => $request->date,
                'payment_method' => $request->payment_method,
                'proof_files' => $proofFiles,
                'note' => $request->note,
                'expense_type' => OpsExpenseType::INTERNAL,
                'created_by' => $request->user()->id,
                'company_id' => $companyId,
            ]);

            return response()->json([
                'success' => true,
                'message' => __('operational.expenses.admin_stored'),
                'data' => new OpsExpenseResource(
                    $expense->load(['createdBy'])
                ),
            ], 201);
        }

        $mandor = $this->subCompanyService->resolveMandor($request->mandor_uuid, $companyId);
        $subCompany = $this->subCompanyService->resolveForAdmin(
            $request->sub_company_uuid,
            $companyId,
            $mandor->id
        );

        DB::beginTransaction();
        try {
            $expense = OpsExpense::create([
                'name' => $request->name,
                'amount' => $request->amount,
                'date' => $request->date,
                'payment_method' => $request->payment_method,
                'proof_files' => $proofFiles,
                'note' => $request->note,
                'expense_type' => OpsExpenseType::MANDOR,
                'mandor_id' => $mandor->id,
                'sub_company_id' => $subCompany->id,
                'created_by' => $request->user()->id,
                'company_id' => $companyId,
            ]);

            $confirmation = OpsTransferConfirmation::create([
                'confirmable_type' => $expense->getMorphClass(),
                'confirmable_id' => $expense->id,
                'status' => OpsTransferConfirmationStatus::PENDING,
                'company_id' => $companyId,
            ]);

            $this->notificationService->notifyMandorIncomePending($mandor, $expense, $confirmation);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('operational.expenses.mandor_transfer_stored'),
                'data' => new OpsExpenseResource(
                    $expense->load(['mandor', 'subCompany', 'createdBy', 'transferConfirmation'])
                ),
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    protected function storeAsMandor(OpsExpenseRequest $request)
    {
        $user = $request->user();
        $subCompany = $this->subCompanyService->resolveForMandor($request->sub_company_uuid, $user);
        $wallet = $this->walletService->getOrCreateWallet($user, $subCompany);

        if ((float) $wallet->balance < (float) $request->amount) {
            $this->notificationService->notifyAdminsInsufficientBalance(
                $user,
                (float) $request->amount,
                $request->name
            );

            return response()->json([
                'success' => false,
                'message' => __('operational.expenses.blocked_insufficient_balance'),
                'code' => 422,
            ], 422);
        }

        DB::beginTransaction();

        try {
            $expense = OpsExpense::create([
                'name' => $request->name,
                'amount' => $request->amount,
                'date' => $request->date,
                'payment_method' => $request->payment_method,
                'proof_files' => $this->storeProofFilesFromRequest($request, 'expense', 'mandor'),
                'note' => $request->note,
                'expense_type' => OpsExpenseType::INTERNAL,
                'mandor_id' => $user->id,
                'sub_company_id' => $subCompany->id,
                'created_by' => $user->id,
                'company_id' => $user->company_id,
            ]);

            $this->walletService->debit(
                $wallet->fresh(),
                (float) $request->amount,
                OpsWalletTransactionType::CASH,
                $expense,
                $user,
                $expense->name
            );

            $this->notificationService->notifyAdminsExpenseCreated($user, $expense);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('operational.expenses.stored'),
                'data' => new OpsExpenseResource(
                    $expense->load(['mandor', 'subCompany', 'createdBy'])
                ),
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    protected function updateAsAdmin(OpsExpenseRequest $request, OpsExpense $opsExpense)
    {
        if ($opsExpense->expense_type === OpsExpenseType::MANDOR) {
            if ($response = $this->assertMandorTransferEditable($opsExpense)) {
                return $response;
            }

            return $this->updateAdminMandorTransfer($request, $opsExpense);
        }

        return $this->updateAdminInternal($request, $opsExpense);
    }

    protected function updateAdminInternal(OpsExpenseRequest $request, OpsExpense $opsExpense)
    {
        $oldData = $this->auditablePayload($opsExpense);

        $updateData = [
            'name' => $request->name,
            'amount' => $request->amount,
            'date' => $request->date,
            'payment_method' => $request->payment_method,
            'note' => $request->note,
        ];

        if ($proofFiles = $this->replaceProofFilesOnUpdate($request, $opsExpense, 'expense', 'admin')) {
            $updateData['proof_files'] = $proofFiles;
        }

        $opsExpense->update($updateData);

        OpsEditLog::create([
            'loggable_type' => 'ops_expenses',
            'loggable_id' => $opsExpense->id,
            'reason' => $request->reason ?? '-',
            'old_data' => $oldData,
            'new_data' => $this->auditablePayload($opsExpense),
            'edited_by' => $request->user()->id,
            'company_id' => $request->user()->company_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('operational.expenses.updated'),
            'data' => new OpsExpenseResource(
                $opsExpense->fresh()->load(['createdBy', 'editLogs'])
            ),
        ]);
    }

    protected function updateAdminMandorTransfer(OpsExpenseRequest $request, OpsExpense $opsExpense)
    {
        $opsExpense->loadMissing('transferIncome.transferConfirmation');
        $income = $opsExpense->transferIncome;

        $oldData = $this->auditablePayload($opsExpense);

        $updateData = [
            'name' => $request->name,
            'amount' => $request->amount,
            'date' => $request->date,
            'payment_method' => $request->payment_method,
            'note' => $request->note,
        ];

        if ($proofFiles = $this->replaceProofFilesOnUpdate($request, $opsExpense, 'expense', 'admin')) {
            $updateData['proof_files'] = $proofFiles;
        }

        DB::beginTransaction();
        try {
            $opsExpense->update($updateData);

            if ($income) {
                $incomeUpdate = [
                    'name' => $request->name,
                    'amount' => $request->amount,
                    'date' => $request->date,
                    'payment_method' => $request->payment_method,
                    'note' => $request->note,
                ];

                if (isset($updateData['proof_files'])) {
                    $this->fileService->deleteProofs($income->proof_files ?? []);
                    $incomeUpdate['proof_files'] = $updateData['proof_files'];
                }

                $income->update($incomeUpdate);
            }

            OpsEditLog::create([
                'loggable_type' => 'ops_expenses',
                'loggable_id' => $opsExpense->id,
                'reason' => $request->reason ?? '-',
                'old_data' => $oldData,
                'new_data' => $this->auditablePayload($opsExpense),
                'edited_by' => $request->user()->id,
                'company_id' => $request->user()->company_id,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('operational.expenses.updated'),
                'data' => new OpsExpenseResource(
                    $opsExpense->fresh()->load(['mandor', 'subCompany', 'createdBy', 'transferIncome.transferConfirmation', 'editLogs'])
                ),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    protected function updateAsMandor(OpsExpenseRequest $request, OpsExpense $opsExpense)
    {
        $user = $request->user();
        $this->authorizeExpenseAccess($opsExpense, $user->role);

        if ($opsExpense->expense_type !== OpsExpenseType::INTERNAL) {
            abort(response()->json([
                'success' => false,
                'message' => __('operational.expenses.not_editable'),
                'code' => 422,
            ], 422));
        }

        $subCompany = $opsExpense->subCompany
            ?? $this->subCompanyService->resolveForMandor($request->sub_company_uuid, $user);
        $wallet = $this->walletService->getOrCreateWallet($user, $subCompany);
        $oldAmount = (float) $opsExpense->amount;
        $newAmount = (float) $request->amount;
        $difference = round($newAmount - $oldAmount, 2);

        if ($difference > 0 && (float) $wallet->balance < $difference) {
            return response()->json([
                'success' => false,
                'message' => __('operational.expenses.blocked_insufficient_balance'),
                'code' => 422,
            ], 422);
        }

        DB::beginTransaction();

        try {
            $oldData = $this->auditablePayload($opsExpense);

            $updateData = [
                'name' => $request->name,
                'amount' => $request->amount,
                'date' => $request->date,
                'payment_method' => $request->payment_method,
                'note' => $request->note,
            ];

            if ($proofFiles = $this->replaceProofFilesOnUpdate($request, $opsExpense, 'expense', 'mandor')) {
                $updateData['proof_files'] = $proofFiles;
            }

            $opsExpense->update($updateData);

            OpsEditLog::create([
                'loggable_type' => 'ops_expenses',
                'loggable_id' => $opsExpense->id,
                'reason' => $request->reason ?? '-',
                'old_data' => $oldData,
                'new_data' => $this->auditablePayload($opsExpense),
                'edited_by' => $user->id,
                'company_id' => $user->company_id,
            ]);

            $this->walletService->adjustBalance(
                $wallet->fresh(),
                $oldAmount,
                $newAmount,
                $opsExpense,
                $user
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('operational.expenses.updated'),
                'data' => new OpsExpenseResource(
                    $opsExpense->fresh()->load(['mandor', 'subCompany', 'createdBy'])
                ),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    protected function destroyAsAdmin(OpsExpense $opsExpense)
    {
        if ($opsExpense->expense_type === OpsExpenseType::MANDOR) {
            if ($response = $this->assertMandorTransferEditable($opsExpense)) {
                return $response;
            }

            DB::beginTransaction();
            try {
                $opsExpense->loadMissing('transferIncome.transferConfirmation');
                $income = $opsExpense->transferIncome;

                $this->deleteRecordProofs($opsExpense);

                if ($income) {
                    $income->transferConfirmation?->delete();
                    $income->forceDelete();
                }

                $opsExpense->forceDelete();

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => __('operational.expenses.deleted'),
                ]);
            } catch (\Throwable $th) {
                DB::rollBack();
                throw $th;
            }
        }

        $this->deleteRecordProofs($opsExpense);

        $opsExpense->forceDelete();

        return response()->json([
            'success' => true,
            'message' => __('operational.expenses.deleted'),
        ]);
    }

    protected function destroyAsMandor(OpsExpense $opsExpense)
    {
        DB::beginTransaction();
        try {
            $this->authorizeExpenseAccess($opsExpense, request()->user()->role);

            if ($opsExpense->expense_type !== OpsExpenseType::INTERNAL) {
                abort(response()->json([
                    'success' => false,
                    'message' => __('operational.expenses.not_editable'),
                    'code' => 422,
                ], 422));
            }

            $opsExpense->loadMissing('subCompany');

            if (!$opsExpense->subCompany) {
                return response()->json([
                    'success' => false,
                    'message' => __('operational.validation.sub_company_uuid_not_found'),
                    'code' => 422,
                ], 422);
            }

            $this->deleteRecordProofs($opsExpense);

            $subCompany = $opsExpense->subCompany;
            $wallet = $this->walletService->getOrCreateWallet($opsExpense->mandor, $subCompany);

            $this->walletService->credit(
                $wallet,
                $opsExpense->amount,
                OpsWalletTransactionType::CASH,
                $opsExpense,
                $opsExpense->mandor,
                $opsExpense->name
            );

            $opsExpense->forceDelete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('operational.expenses.deleted'),
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    protected function assertMandorTransferEditable(OpsExpense $opsExpense): ?\Illuminate\Http\JsonResponse
    {
        $opsExpense->loadMissing('transferIncome.transferConfirmation');

        $status = $opsExpense->transferIncome?->transferConfirmation?->status;

        if ($status !== OpsTransferConfirmationStatus::PENDING) {
            return response()->json([
                'success' => false,
                'message' => __('operational.expenses.not_pending'),
                'code' => 422,
            ], 422);
        }

        return null;
    }

    protected function authorizeExpenseAccess(OpsExpense $expense, ?Role $mandorOnly = null): void
    {
        $user = request()->user();
        $isMandorOrKepala = in_array($user->role, [Role::MANDOR, Role::KEPALA_MANDOR]);

        if ($mandorOnly !== null && !$isMandorOrKepala) {
            abort(response()->json([
                'success' => false,
                'message' => __('operational.expenses.not_accessible'),
                'code' => 403,
            ], 403));
        }

        if (!$isMandorOrKepala) {
            return;
        }

        $expense->loadMissing('subCompany');

        if (!$this->mandorCanAccessOperationalRecord($user, $expense->mandor_id, $expense->subCompany)) {
            abort(response()->json([
                'success' => false,
                'message' => __('operational.expenses.not_accessible'),
                'code' => 403,
            ], 403));
        }
    }
}
