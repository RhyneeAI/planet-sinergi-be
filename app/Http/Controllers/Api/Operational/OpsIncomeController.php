<?php

namespace App\Http\Controllers\Api\Operational;

use App\Enums\OpsSourceType;
use App\Enums\OpsWalletTransactionType;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operational\OpsIncomeRequest;
use App\Http\Resources\Operational\OpsIncomeResource;
use App\Models\OpsEditLog;
use App\Models\OpsIncome;
use App\Services\Operational\OpsFileService;
use App\Services\Operational\OpsWalletService;
use App\Services\SubCompanyService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OpsIncomeController extends Controller
{
    use ReturnsEmptyShowResponse;
    use ScopesOperationalBySubCompany;
    use HandlesOperationalProofFiles;

    protected array $sortableColumns = ['name', 'date', 'amount', 'source_type'];

    public function __construct(
        protected OpsFileService $fileService,
        protected SubCompanyService $subCompanyService,
        protected OpsWalletService $walletService,
    ) {}

    public function index(Request $request)
    {
        return $this->indexResponse($request);
    }

    public function store(OpsIncomeRequest $request)
    {
        if ($response = $this->validateStoreWindow($request->date, 'store')) {
            return $response;
        }

        if ($request->user()->role === Role::MANDOR) {
            return $this->storeAsMandor($request);
        }

        return $this->storeAsAdmin($request);
    }

    public function show(Request $request, string $uuid)
    {
        $opsIncome = OpsIncome::where('uuid', $uuid)->first();

        if (!$opsIncome) {
            return $this->emptyShowResponse(__('operational.incomes.detail'));
        }

        if ($request->user()->role === Role::MANDOR) {
            $this->authorizeMandorIncomeAccess($opsIncome);
        }

        return $this->showResponse($opsIncome);
    }

    public function update(OpsIncomeRequest $request, string $uuid)
    {
        $opsIncome = OpsIncome::where('uuid', $uuid)->first();

        if (!$opsIncome) {
            return response()->json([
                'success' => false,
                'message' => __('operational.incomes.not_found'),
                'code' => 404,
            ], 404);
        }

        if ($response = $this->validateStoreWindow($request->date, 'edit')) {
            return $response;
        }

        if ($request->user()->role === Role::MANDOR) {
            return $this->updateAsMandor($request, $opsIncome);
        }

        return $this->updateAsAdmin($request, $opsIncome);
    }

    public function destroy(Request $request, string $uuid)
    {
        $opsIncome = OpsIncome::where('uuid', $uuid)->first();

        if (!$opsIncome) {
            return response()->json([
                'success' => false,
                'message' => __('operational.incomes.not_found'),
                'code' => 404,
            ], 404);
        }

        if ($request->user()->role === Role::MANDOR) {
            return $this->destroyAsMandor($opsIncome);
        }

        return $this->destroyAsAdmin($opsIncome);
    }

    protected function storeAsAdmin(OpsIncomeRequest $request)
    {
        $companyId = $request->user()->company_id;
        $mandorId = null;
        $subCompanyId = null;

        if ($request->filled('mandor_uuid')) {
            $mandor = $this->subCompanyService->resolveMandor($request->mandor_uuid, $companyId);
            $subCompany = $this->subCompanyService->resolveForAdmin(
                $request->sub_company_uuid,
                $companyId,
                $mandor->id
            );
            $mandorId = $mandor->id;
            $subCompanyId = $subCompany->id;
        }

        $income = OpsIncome::create([
            'name' => $request->name,
            'amount' => $request->amount,
            'date' => $request->date,
            'proof_files' => $this->storeProofFilesFromRequest($request),
            'note' => $request->note,
            'source_type' => OpsSourceType::INTERNAL,
            'mandor_id' => $mandorId,
            'sub_company_id' => $subCompanyId,
            'created_by' => $request->user()->id,
            'company_id' => $companyId,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('operational.incomes.stored'),
            'data' => new OpsIncomeResource(
                $income->load(['mandor', 'subCompany', 'createdBy'])
            ),
        ], 201);
    }

    protected function storeAsMandor(OpsIncomeRequest $request)
    {
        $user = $request->user();
        $subCompany = $this->subCompanyService->resolveForMandor($request->sub_company_uuid, $user);
        $wallet = $this->walletService->getOrCreateWallet($user, $subCompany);

        DB::beginTransaction();
        try {
            $income = OpsIncome::create([
                'name' => $request->name,
                'amount' => $request->amount,
                'date' => $request->date,
                'proof_files' => $this->storeProofFilesFromRequest($request),
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

    protected function updateAsAdmin(OpsIncomeRequest $request, OpsIncome $opsIncome)
    {
        if ($response = $this->assertAdminEditableIncome($opsIncome)) {
            return $response;
        }

        $companyId = $request->user()->company_id;
        $mandorId = null;
        $subCompanyId = null;

        if ($request->filled('mandor_uuid')) {
            $mandor = $this->subCompanyService->resolveMandor($request->mandor_uuid, $companyId);
            $subCompany = $this->subCompanyService->resolveForAdmin(
                $request->sub_company_uuid,
                $companyId,
                $mandor->id
            );
            $mandorId = $mandor->id;
            $subCompanyId = $subCompany->id;
        }

        $payload = [
            'name' => $request->name,
            'amount' => $request->amount,
            'date' => $request->date,
            'note' => $request->note,
            'mandor_id' => $mandorId,
            'sub_company_id' => $subCompanyId,
        ];

        if ($proofFiles = $this->replaceProofFilesOnUpdate($request, $opsIncome)) {
            $payload['proof_files'] = $proofFiles;
        }

        $oldData = $this->auditablePayload($opsIncome);

        $opsIncome->update($payload);

        OpsEditLog::create([
            'loggable_type' => 'ops_incomes',
            'loggable_id' => $opsIncome->id,
            'reason' => $request->reason ?? '-',
            'old_data' => $oldData,
            'new_data' => $this->auditablePayload($opsIncome),
            'edited_by' => $request->user()->id,
            'company_id' => $companyId,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('operational.incomes.updated'),
            'data' => new OpsIncomeResource(
                $opsIncome->load(['mandor', 'subCompany', 'createdBy', 'editLogs'])
            ),
        ]);
    }

    protected function updateAsMandor(OpsIncomeRequest $request, OpsIncome $opsIncome)
    {
        $user = $request->user();
        $this->authorizeMandorIncomeAccess($opsIncome, editable: true);

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
            $oldData = $this->auditablePayload($opsIncome);

            $updateData = [
                'name' => $request->name,
                'amount' => $request->amount,
                'date' => $request->date,
                'note' => $request->note,
                'sub_company_id' => $subCompany->id,
            ];

            if ($proofFiles = $this->replaceProofFilesOnUpdate($request, $opsIncome)) {
                $updateData['proof_files'] = $proofFiles;
            }

            $opsIncome->update($updateData);

            OpsEditLog::create([
                'loggable_type' => 'ops_incomes',
                'loggable_id' => $opsIncome->id,
                'reason' => $request->reason ?? '-',
                'old_data' => $oldData,
                'new_data' => $this->auditablePayload($opsIncome),
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

    protected function destroyAsAdmin(OpsIncome $opsIncome)
    {
        if ($response = $this->assertAdminEditableIncome($opsIncome)) {
            return $response;
        }

        $this->deleteRecordProofs($opsIncome);

        $opsIncome->delete();

        return response()->json([
            'success' => true,
            'message' => __('operational.incomes.deleted'),
        ]);
    }

    protected function destroyAsMandor(OpsIncome $opsIncome)
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

            $this->deleteRecordProofs($opsIncome);

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

    protected function assertAdminEditableIncome(OpsIncome $income): ?\Illuminate\Http\JsonResponse
    {
        if ($income->source_type !== OpsSourceType::INTERNAL) {
            return response()->json([
                'success' => false,
                'message' => __('operational.incomes.not_editable_by_admin'),
                'code' => 422,
            ], 422);
        }

        return null;
    }

    protected function authorizeMandorIncomeAccess(OpsIncome $income, bool $editable = false): void
    {
        $user = request()->user();
        $income->loadMissing('subCompany');

        if (!$this->mandorCanAccessOperationalRecord($user, $income->mandor_id, $income->subCompany)) {
            abort(response()->json([
                'success' => false,
                'message' => __('operational.incomes.not_accessible'),
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
