<?php

namespace App\Http\Controllers\Api\Operational;

use App\Enums\OpsSourceType;
use App\Enums\OpsTransferConfirmationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operational\OpsIncomeStoreRequest;
use App\Http\Requests\Operational\OpsIncomeUpdateRequest;
use App\Http\Resources\Operational\OpsIncomeResource;
use App\Models\OpsEditLog;
use App\Models\OpsIncome;
use App\Models\OpsTransferConfirmation;
use App\Models\User;
use App\Services\Operational\OpsFileService;
use App\Services\Operational\OpsNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OpsIncomeController extends Controller
{
    protected array $sortableColumns = ['name', 'date', 'amount', 'source_type'];

    public function __construct(
        protected OpsFileService $fileService,
        protected OpsNotificationService $notificationService,
    ) {}

    public function index(Request $request)
    {
        $orderByKey = in_array($request->input('order_by_key', 'date'), $this->sortableColumns)
            ? $request->input('order_by_key', 'date')
            : 'date';
        $orderByValue = strtoupper($request->input('order_by_value', 'DESC')) === 'ASC' ? 'DESC' : 'ASC';

        $incomes = OpsIncome::with(['mandor', 'createdBy', 'transferConfirmation', 'editLogs'])
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
            'message' => __('operational.incomes.list'),
            'data' => OpsIncomeResource::collection($incomes),
        ]);
    }

    public function store(OpsIncomeStoreRequest $request)
    {
        DB::beginTransaction();

        try {
            $mandor = User::where('uuid', $request->mandor_uuid)
                ->where('company_id', $request->user()->company_id)
                ->where('role', \App\Enums\Role::MANDOR)
                ->firstOrFail();

            $income = OpsIncome::create([
                'name' => $request->name,
                'amount' => $request->amount,
                'date' => $request->date,
                'proof_file' => $this->fileService->storeProof($request->file('proof_file')),
                'note' => $request->note,
                'source_type' => OpsSourceType::MANDOR,
                'mandor_id' => $mandor->id,
                'created_by' => $request->user()->id,
                'company_id' => $request->user()->company_id,
            ]);

            $confirmation = OpsTransferConfirmation::create([
                'confirmable_type' => $income->getMorphClass(),
                'confirmable_id' => $income->id,
                'status' => OpsTransferConfirmationStatus::PENDING,
                'company_id' => $request->user()->company_id,
            ]);

            $this->notificationService->notifyMandorIncomePending($mandor, $income, $confirmation);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('operational.incomes.stored'),
                'data' => new OpsIncomeResource(
                    $income->load(['mandor', 'createdBy', 'transferConfirmation'])
                ),
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function show(OpsIncome $opsIncome)
    {


        return response()->json([
            'success' => true,
            'message' => __('operational.incomes.detail'),
            'data' => new OpsIncomeResource(
                $opsIncome->load(['mandor', 'createdBy', 'transferConfirmation', 'editLogs'])
            ),
        ]);
    }

    public function update(OpsIncomeUpdateRequest $request, OpsIncome $opsIncome)
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

            $mandor = User::where('uuid', $request->mandor_uuid)
                ->where('company_id', $request->user()->company_id)
                ->where('role', \App\Enums\Role::MANDOR)
                ->firstOrFail();

            $payload = [
                'name' => $request->name,
                'amount' => $request->amount,
                'date' => $request->date,
                'mandor_id' => $mandor->id,
                'created_by' => $request->user()->id,
                'company_id' => $request->user()->company_id,
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
                'company_id' => $request->user()->company_id,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('operational.incomes.updated'),
                'data' => new OpsIncomeResource(
                    $opsIncome->load(['mandor', 'createdBy', 'transferConfirmation'])
                ),
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function destroy(OpsIncome $opsIncome)
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
}
