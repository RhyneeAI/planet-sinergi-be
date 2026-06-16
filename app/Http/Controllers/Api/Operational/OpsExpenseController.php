<?php

namespace App\Http\Controllers\Api\Operational;

use App\Enums\OpsExpenseType;
use App\Enums\OpsWalletTransactionType;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operational\OpsExpenseStoreRequest;
use App\Http\Requests\Operational\OpsExpenseUpdateRequest;
use App\Http\Resources\Operational\OpsExpenseResource;
use App\Models\OpsEditLog;
use App\Models\OpsExpense;
use App\Services\Operational\OpsFileService;
use App\Services\Operational\OpsNotificationService;
use App\Services\SubCompanyService;
use App\Services\Operational\OpsWalletService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OpsExpenseController extends Controller
{
    use ScopesOperationalBySubCompany;

    protected array $sortableColumns = ['name', 'date', 'amount', 'expense_type'];

    public function __construct(
        protected OpsFileService $fileService,
        protected OpsWalletService $walletService,
        protected OpsNotificationService $notificationService,
        protected SubCompanyService $subCompanyService,
    ) {}

    public function adminIndex(Request $request)
    {
        return $this->indexResponse($request);
    }

    public function mandorIndex(Request $request)
    {
        return $this->indexResponse($request);
    }

    public function mandorStore(OpsExpenseStoreRequest $request)
    {
        $editWindowDays = config('operational.expense_edit_window_days');
        $requestDate = Carbon::parse($request->date)->startOfDay();
        $limitDate = now()->subDays($editWindowDays)->startOfDay();
        if ($requestDate->lt($limitDate)) {
            return response()->json([
                'success' => false,
                'message' => __('operational.expenses.store_window_expired', ['days' => $editWindowDays]),
                'code' => 422,
            ], 422);
        }

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
                'proof_file' => $this->fileService->storeProof($request->file('proof_file')),
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

    public function adminShow(OpsExpense $opsExpense)
    {
        return $this->showResponse($opsExpense);
    }

    public function mandorShow(OpsExpense $opsExpense)
    {
        $this->authorizeExpenseAccess($opsExpense);

        return $this->showResponse($opsExpense);
    }

    public function mandorUpdate(OpsExpenseUpdateRequest $request, OpsExpense $opsExpense)
    {
        $user = $request->user();
        $this->authorizeExpenseAccess($opsExpense, Role::MANDOR);

        $editWindowDays = config('operational.expense_edit_window_days');

        $requestDate = Carbon::parse($request->date)->startOfDay();
        $limitDate = now()->subDays($editWindowDays)->startOfDay();
        if ($requestDate->lt($limitDate)) {
            return response()->json([
                'success' => false,
                'message' => __('operational.expenses.edit_window_expired', ['days' => $editWindowDays]),
                'code' => 422,
            ], 422);
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
            $oldData = $opsExpense->only(['name', 'amount', 'date', 'proof_file', 'note']);

            $updateData = [
                'name' => $request->name,
                'amount' => $request->amount,
                'date' => $request->date,
                'note' => $request->note,
            ];

            if ($request->proof_file) {
                $updateData['proof_file'] = $this->fileService->storeProof($request->file('proof_file'));

                $this->fileService->deleteProof($opsExpense->proof_file);
            }

            $opsExpense->update($updateData);

            OpsEditLog::create([
                'loggable_type' => 'ops_expenses',
                'loggable_id' => $opsExpense->id,
                'reason' => $request->reason ?? '-',
                'old_data' => $oldData,
                'new_data' => $opsExpense->only(['name', 'amount', 'date', 'proof_file', 'note']),
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

    public function mandorDestroy(OpsExpense $opsExpense)
    {
        DB::beginTransaction();
        try {
            $this->authorizeExpenseAccess($opsExpense, Role::MANDOR);

            $opsExpense->loadMissing('subCompany');

            if (!$opsExpense->subCompany) {
                return response()->json([
                    'success' => false,
                    'message' => __('operational.validation.sub_company_uuid_not_found'),
                    'code' => 422,
                ], 422);
            }

            $this->fileService->deleteProof($opsExpense->proof_file);

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

            $opsExpense->delete();

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

    protected function indexResponse(Request $request)
    {
        $orderByKey = in_array($request->input('order_by_key', 'date'), $this->sortableColumns)
            ? $request->input('order_by_key', 'date')
            : 'date';
        $orderByValue = strtoupper($request->input('order_by_value', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $expenses = OpsExpense::with(['mandor', 'subCompany', 'createdBy', 'editLogs'])
            ->withCount('editLogs')
            ->when(true, fn ($query) => $this->applySubCompanyFilter($query, $request))
            ->when($request->date_from, fn($q, $date) => $q->whereDate('date', '>=', $date))
            ->when($request->date_to, fn($q, $date) => $q->whereDate('date', '<=', $date))
            ->when(
                $request->mandor_uuid,
                fn($q, $uuid) =>
                $q->whereHas('mandor', fn($m) => $m->where('uuid', $uuid))
            )
            ->when($request->search, function ($query, $search) {
                $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%']);
            })
            ->orderBy($orderByKey, $orderByValue)
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => __('operational.expenses.list'),
            'data' => OpsExpenseResource::collection($expenses),
        ]);
    }

    protected function showResponse(OpsExpense $opsExpense)
    {
        return response()->json([
            'success' => true,
            'message' => __('operational.expenses.detail'),
            'data' => new OpsExpenseResource(
                $opsExpense->load(['mandor', 'subCompany', 'createdBy', 'editLogs'])
            ),
        ]);
    }

    protected function authorizeExpenseAccess(OpsExpense $expense, ?Role $mandorOnly = null): void
    {
        $user = request()->user();

        if ($mandorOnly === Role::MANDOR && $user->role !== Role::MANDOR) {
            abort(response()->json([
                'success' => false,
                'message' => 'You don\'t have permission to access this resource.',
                'code' => 403,
            ], 403));
        }

        if ($user->role === Role::MANDOR && $expense->subCompany?->mandor_id !== $user->id) {
            abort(response()->json([
                'success' => false,
                'message' => 'You don\'t have permission to access this resource.',
                'code' => 403,
            ], 403));
        }
    }
}
