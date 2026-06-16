<?php

namespace App\Http\Controllers\Api\Operational;

use App\Enums\OpsSourceType;
use App\Enums\OpsTransferConfirmationStatus;
use App\Enums\OpsWalletTransactionType;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operational\OpsIncomeStoreRequest;
use App\Http\Requests\Operational\OpsIncomeUpdateRequest;
use App\Http\Requests\Operational\OpsMandorIncomeStoreRequest;
use App\Http\Requests\Operational\OpsMandorIncomeUpdateRequest;
use App\Http\Resources\Operational\OpsIncomeResource;
use App\Models\OpsEditLog;
use App\Models\OpsIncome;
use App\Models\OpsTransferConfirmation;
use App\Services\Operational\OpsFileService;
use App\Services\Operational\OpsNotificationService;
use App\Services\Operational\OpsWalletService;
use App\Services\SubCompanyService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OpsIncomeController extends Controller
{
    use ScopesOperationalBySubCompany;

    protected array $sortableColumns = ['name', 'date', 'amount', 'source_type'];

    public function __construct(
        protected OpsFileService $fileService,
        protected OpsNotificationService $notificationService,
        protected SubCompanyService $subCompanyService,
        protected OpsWalletService $walletService,
    ) {}

    public function adminIndex(Request $request)
    {
        return $this->indexResponse($request);
    }

    public function mandorIndex(Request $request)
    {
        return $this->indexResponse($request);
    }

    public function adminStore(OpsIncomeStoreRequest $request)
    {
        if ($response = $this->validateStoreWindow($request->date, 'store')) {
            return $response;
        }

        DB::beginTransaction();
        try {
            $companyId = $request->user()->company_id;
            $mandor = $this->subCompanyService->resolveMandor($request->mandor_uuid, $companyId);
            $subCompany = $this->subCompanyService->resolveForAdmin(
                $request->sub_company_uuid,
                $companyId,
                $mandor->id
            );

            $income = OpsIncome::create([
                'name' => $request->name,
                'amount' => $request->amount,
                'date' => $request->date,
                'proof_file' => $this->fileService->storeProof($request->file('proof_file')),
                'note' => $request->note,
                'source_type' => OpsSourceType::MANDOR,
                'mandor_id' => $mandor->id,
                'sub_company_id' => $subCompany->id,
                'created_by' => $request->user()->id,
                'company_id' => $companyId,
            ]);

            $confirmation = OpsTransferConfirmation::create([
                'confirmable_type' => $income->getMorphClass(),
                'confirmable_id' => $income->id,
                'status' => OpsTransferConfirmationStatus::PENDING,
                'company_id' => $companyId,
            ]);

            $this->notificationService->notifyMandorIncomePending($mandor, $income, $confirmation);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('operational.incomes.stored'),
                'data' => new OpsIncomeResource(
                    $income->load(['mandor', 'subCompany', 'createdBy', 'transferConfirmation'])
                ),
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function mandorStore(OpsMandorIncomeStoreRequest $request)
    {
        if ($response = $this->validateStoreWindow($request->date, 'store')) {
            return $response;
        }

        $user = $request->user();
        $subCompany = $this->subCompanyService->resolveForMandor($request->sub_company_uuid, $user);
        $wallet = $this->walletService->getOrCreateWallet($user, $subCompany);

        DB::beginTransaction();
        try {
            $income = OpsIncome::create([
                'name' => $request->name,
                'amount' => $request->amount,
                'date' => $request->date,
                'proof_file' => $this->fileService->storeProof($request->file('proof_file')),
                'note' => $request->note,
                'source_type' => OpsSourceType::INTERNAL,
                'mandor_id' => $user->id,
                'sub_company_id' => $subCompany->id,
                'created_by' => $user->id,
                'company_id' => $user->company_id,
            ]);

            $this->walletService->credit(
                $wallet,
                (float) $request->amount,
                OpsWalletTransactionType::CASH,
                $income,
                $user,
                $income->name
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('operational.incomes.mandor_stored'),
                'data' => new OpsIncomeResource(
                    $income->load(['mandor', 'subCompany', 'createdBy'])
                ),
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function adminShow(OpsIncome $opsIncome)
    {
        return $this->showResponse($opsIncome);
    }

    public function mandorShow(OpsIncome $opsIncome)
    {
        $this->authorizeMandorIncomeAccess($opsIncome);

        return $this->showResponse($opsIncome);
    }

    public function adminUpdate(OpsIncomeUpdateRequest $request, OpsIncome $opsIncome)
    {
        if ($response = $this->validateStoreWindow($request->date, 'edit')) {
            return $response;
        }

        DB::beginTransaction();
        try {
            if ($opsIncome->transferConfirmation->status !== OpsTransferConfirmationStatus::PENDING) {
                return response()->json([
                    'success' => false,
                    'message' => __('operational.incomes.not_pending'),
                    'code' => 422,
                ], 422);
            }

            $companyId = $request->user()->company_id;
            $mandor = $this->subCompanyService->resolveMandor($request->mandor_uuid, $companyId);
            $subCompany = $this->subCompanyService->resolveForAdmin(
                $request->sub_company_uuid,
                $companyId,
                $mandor->id
            );

            $payload = [
                'name' => $request->name,
                'amount' => $request->amount,
                'date' => $request->date,
                'note' => $request->note,
                'mandor_id' => $mandor->id,
                'sub_company_id' => $subCompany->id,
                'created_by' => $request->user()->id,
                'company_id' => $companyId,
            ];

            if ($request->hasFile('proof_file')) {
                $payload['proof_file'] = $this->fileService->storeProof($request->file('proof_file'));
                $this->fileService->deleteProof($opsIncome->proof_file);
            }

            $oldData = $opsIncome->only(['name', 'amount', 'date', 'proof_file', 'note']);

            $opsIncome->update($payload);

            OpsEditLog::create([
                'loggable_type' => 'ops_incomes',
                'loggable_id' => $opsIncome->id,
                'reason' => $request->reason ?? '-',
                'old_data' => $oldData,
                'new_data' => $opsIncome->only(['name', 'amount', 'date', 'proof_file', 'note']),
                'edited_by' => $request->user()->id,
                'company_id' => $companyId,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('operational.incomes.updated'),
                'data' => new OpsIncomeResource(
                    $opsIncome->load(['mandor', 'subCompany', 'createdBy', 'transferConfirmation'])
                ),
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function mandorUpdate(OpsMandorIncomeUpdateRequest $request, OpsIncome $opsIncome)
    {
        $user = $request->user();
        $this->authorizeMandorIncomeAccess($opsIncome, editable: true);

        if ($response = $this->validateStoreWindow($request->date, 'edit')) {
            return $response;
        }

        $subCompany = $request->filled('sub_company_uuid')
            ? $this->subCompanyService->resolveForMandor($request->sub_company_uuid, $user)
            : $opsIncome->subCompany;

        if (!$subCompany) {
            return response()->json([
                'success' => false,
                'message' => __('operational.validation.sub_company_uuid_not_found'),
                'code' => 422,
            ], 422);
        }

        $wallet = $this->walletService->getOrCreateWallet($user, $subCompany);
        $oldAmount = (float) $opsIncome->amount;
        $newAmount = (float) $request->amount;
        $difference = round($newAmount - $oldAmount, 2);

        if ($difference < 0 && (float) $wallet->balance < abs($difference)) {
            return response()->json([
                'success' => false,
                'message' => __('operational.wallet.insufficient_balance'),
                'code' => 422,
            ], 422);
        }

        DB::beginTransaction();
        try {
            $oldData = $opsIncome->only(['name', 'amount', 'date', 'proof_file', 'note']);

            $updateData = [
                'name' => $request->name,
                'amount' => $request->amount,
                'date' => $request->date,
                'note' => $request->note,
                'sub_company_id' => $subCompany->id,
            ];

            if ($request->hasFile('proof_file')) {
                $updateData['proof_file'] = $this->fileService->storeProof($request->file('proof_file'));
                $this->fileService->deleteProof($opsIncome->proof_file);
            }

            $opsIncome->update($updateData);

            OpsEditLog::create([
                'loggable_type' => 'ops_incomes',
                'loggable_id' => $opsIncome->id,
                'reason' => $request->reason ?? '-',
                'old_data' => $oldData,
                'new_data' => $opsIncome->only(['name', 'amount', 'date', 'proof_file', 'note']),
                'edited_by' => $user->id,
                'company_id' => $user->company_id,
            ]);

            $this->walletService->adjustIncomeBalance(
                $wallet->fresh(),
                $oldAmount,
                $newAmount,
                $opsIncome,
                $user
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('operational.incomes.updated'),
                'data' => new OpsIncomeResource(
                    $opsIncome->fresh()->load(['mandor', 'subCompany', 'createdBy'])
                ),
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function adminDestroy(OpsIncome $opsIncome)
    {
        return $this->destroyAdminTransfer($opsIncome);
    }

    public function mandorDestroy(OpsIncome $opsIncome)
    {
        $user = request()->user();
        $this->authorizeMandorIncomeAccess($opsIncome, editable: true);

        DB::beginTransaction();
        try {
            $opsIncome->loadMissing('subCompany');

            if (!$opsIncome->subCompany) {
                return response()->json([
                    'success' => false,
                    'message' => __('operational.validation.sub_company_uuid_not_found'),
                    'code' => 422,
                ], 422);
            }

            $wallet = $this->walletService->getOrCreateWallet($user, $opsIncome->subCompany);

            if ((float) $wallet->balance < (float) $opsIncome->amount) {
                return response()->json([
                    'success' => false,
                    'message' => __('operational.wallet.insufficient_balance'),
                    'code' => 422,
                ], 422);
            }

            if ($opsIncome->proof_file) {
                $this->fileService->deleteProof($opsIncome->proof_file);
            }

            $this->walletService->debit(
                $wallet,
                (float) $opsIncome->amount,
                OpsWalletTransactionType::CASH,
                $opsIncome,
                $user,
                $opsIncome->name
            );

            $opsIncome->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('operational.incomes.deleted'),
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

        $incomes = OpsIncome::with(['mandor', 'subCompany', 'createdBy', 'transferConfirmation', 'editLogs'])
            ->when(true, fn ($query) => $this->applySubCompanyFilter($query, $request))
            ->when($request->date_from, fn($q, $date) => $q->whereDate('date', '>=', $date))
            ->when($request->date_to, fn($q, $date) => $q->whereDate('date', '<=', $date))
            ->when(
                $request->mandor_uuid,
                fn($q, $uuid) =>
                $q->whereHas('mandor', fn($m) => $m->where('uuid', $uuid))
            )
            ->when($request->source_type, fn ($q, $type) => $q->where('source_type', $type))
            ->when($request->search, function ($query, $search) {
                $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%']);
            })
            ->orderBy($orderByKey, $orderByValue)
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => __('operational.incomes.list'),
            'data' => OpsIncomeResource::collection($incomes),
        ]);
    }

    protected function showResponse(OpsIncome $opsIncome)
    {
        return response()->json([
            'success' => true,
            'message' => __('operational.incomes.detail'),
            'data' => new OpsIncomeResource(
                $opsIncome->load(['mandor', 'subCompany', 'createdBy', 'transferConfirmation', 'editLogs'])
            ),
        ]);
    }

    protected function destroyAdminTransfer(OpsIncome $opsIncome)
    {
        DB::beginTransaction();
        try {
            if ($opsIncome->transferConfirmation->status !== OpsTransferConfirmationStatus::PENDING) {
                return response()->json([
                    'success' => false,
                    'message' => __('operational.incomes.not_pending'),
                    'code' => 422,
                ], 422);
            }

            if ($opsIncome->proof_file) {
                $this->fileService->deleteProof($opsIncome->proof_file);
            }

            $opsIncome->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('operational.incomes.deleted'),
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    protected function validateStoreWindow(string $date, string $action): ?\Illuminate\Http\JsonResponse
    {
        $editWindowDays = config('operational.expense_edit_window_days');
        $requestDate = Carbon::parse($date)->startOfDay();
        $limitDate = now()->subDays($editWindowDays)->startOfDay();

        if ($requestDate->lt($limitDate)) {
            $messageKey = $action === 'store'
                ? 'operational.incomes.store_window_expired'
                : 'operational.incomes.edit_window_expired';

            return response()->json([
                'success' => false,
                'message' => __($messageKey, ['days' => $editWindowDays]),
                'code' => 422,
            ], 422);
        }

        return null;
    }

    protected function authorizeMandorIncomeAccess(OpsIncome $income, bool $editable = false): void
    {
        $user = request()->user();

        if ($income->mandor_id !== $user->id) {
            abort(response()->json([
                'success' => false,
                'message' => 'You don\'t have permission to access this resource.',
                'code' => 403,
            ], 403));
        }

        if ($editable && $income->source_type !== OpsSourceType::INTERNAL) {
            abort(response()->json([
                'success' => false,
                'message' => __('operational.incomes.not_editable'),
                'code' => 422,
            ], 422));
        }
    }
}
