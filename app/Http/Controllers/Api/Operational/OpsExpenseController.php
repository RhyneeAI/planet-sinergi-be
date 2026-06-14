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
use App\Services\Operational\OpsWalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OpsExpenseController extends Controller
{
    protected array $sortableColumns = ['name', 'date', 'amount', 'expense_type'];

    public function __construct(
        protected OpsFileService $fileService,
        protected OpsWalletService $walletService,
        protected OpsNotificationService $notificationService,
    ) {}

    public function index(Request $request)
    {
        $orderByKey = in_array($request->input('order_by_key', 'date'), $this->sortableColumns)
            ? $request->input('order_by_key', 'date')
            : 'date';
        $orderByValue = strtoupper($request->input('order_by_value', 'DESC')) === 'ASC' ? 'DESC' : 'ASC';

        $user = $request->user();

        $expenses = OpsExpense::with(['mandor', 'createdBy', 'editLogs'])
            ->withCount('editLogs')
            ->when(
                $user->role === Role::MANDOR,
                fn($q) =>
                $q->where('mandor_id', $user->id)
            )
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

    public function store(OpsExpenseStoreRequest $request)
    {
        $user = $request->user();
        $wallet = $this->walletService->getOrCreateWallet($user);

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
                    $expense->load(['mandor', 'createdBy'])
                ),
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function show(OpsExpense $opsExpense)
    {
        $this->authorizeExpenseAccess($opsExpense);

        return response()->json([
            'success' => true,
            'message' => __('operational.expenses.detail'),
            'data' => new OpsExpenseResource(
                $opsExpense->load(['mandor', 'createdBy', 'editLogs'])
            ),
        ]);
    }


    public function update(OpsExpenseUpdateRequest $request, OpsExpense $opsExpense)
    {
        $user = $request->user();
        $this->authorizeExpenseAccess($opsExpense, Role::MANDOR);

        $editWindowDays = config('operational.expense_edit_window_days');
        // $maxEditCount = config('operational.expense_max_edit_count');

        if ($opsExpense->created_at->lt(now()->subDays($editWindowDays))) {
            return response()->json([
                'success' => false,
                'message' => __('operational.expenses.edit_window_expired', ['days' => $editWindowDays]),
                'code' => 422,
            ], 422);
        }

        $editCount = $opsExpense->editLogs()->count();

        // if ($editCount >= $maxEditCount) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => __('operational.expenses.edit_limit_reached'),
        //         'code' => 422,
        //     ], 422);
        // }

        $wallet = $this->walletService->getOrCreateWallet($user);
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
                    $opsExpense->fresh()->load(['mandor', 'createdBy'])
                ),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function destroy(OpsExpense $opsExpense)
    {
        DB::beginTransaction();
        try {
            $this->authorizeExpenseAccess($opsExpense, Role::MANDOR);

            $this->fileService->deleteProof($opsExpense->proof_file);

            $wallet = $this->walletService->getOrCreateWallet($opsExpense->mandor);

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

        if ($user->role === Role::MANDOR && $expense->mandor_id !== $user->id) {
            abort(response()->json([
                'success' => false,
                'message' => 'You don\'t have permission to access this resource.',
                'code' => 403,
            ], 403));
        }
    }
}
