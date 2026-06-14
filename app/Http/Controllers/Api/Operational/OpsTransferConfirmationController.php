<?php

namespace App\Http\Controllers\Api\Operational;

use App\Enums\OpsTransferConfirmationStatus;
use App\Enums\OpsWalletTransactionType;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operational\OpsTransferConfirmationRequest;
use App\Http\Resources\Operational\OpsTransferConfirmationResource;
use App\Models\OpsIncome;
use App\Models\OpsTransferConfirmation;
use App\Services\Operational\OpsFileService;
use App\Services\Operational\OpsWalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OpsTransferConfirmationController extends Controller
{
    public function __construct(
        protected OpsFileService $fileService,
        protected OpsWalletService $walletService,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $confirmations = OpsTransferConfirmation::with(['confirmable', 'confirmedBy'])
            ->when($user->role === Role::MANDOR, function ($query) use ($user) {
                $query->whereHasMorph(
                    'confirmable',
                    [OpsIncome::class],
                    fn($q) => $q->where('mandor_id', $user->id)
                );
            })
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => __('operational.confirmations.list'),
            'data' => OpsTransferConfirmationResource::collection($confirmations),
        ]);
    }

    public function show(OpsTransferConfirmation $opsTransferConfirmation)
    {
        $this->authorizeConfirmationAccess($opsTransferConfirmation);

        return response()->json([
            'success' => true,
            'message' => __('operational.confirmations.detail'),
            'data' => new OpsTransferConfirmationResource(
                $opsTransferConfirmation->load(['confirmable.mandor', 'confirmable.createdBy', 'confirmedBy'])
            ),
        ]);
    }

    public function confirm(
        OpsTransferConfirmationRequest $request,
        OpsTransferConfirmation $opsTransferConfirmation
    ) {
        $user = $request->user();
        $this->authorizeConfirmationAccess($opsTransferConfirmation, Role::MANDOR);

        if ($opsTransferConfirmation->status !== OpsTransferConfirmationStatus::PENDING) {
            return response()->json([
                'success' => false,
                'message' => __('operational.confirmations.already_processed'),
                'code' => 422,
            ], 422);
        }

        $income = $opsTransferConfirmation->confirmable;

        if (!$income instanceof OpsIncome) {
            return response()->json([
                'success' => false,
                'message' => __('operational.confirmations.already_processed'),
                'code' => 422,
            ], 422);
        }

        if (round((float) $request->confirmed_amount, 2) !== round((float) $income->amount, 2)) {
            return response()->json([
                'success' => false,
                'message' => __('operational.confirmations.amount_mismatch'),
                'code' => 422,
            ], 422);
        }

        DB::beginTransaction();

        try {
            $opsTransferConfirmation->update([
                'status' => OpsTransferConfirmationStatus::CONFIRMED,
                'mandor_proof_file' => $this->fileService->storeProof($request->file('mandor_proof_file')),
                'confirmed_at' => now(),
                'note' => $request->note,
                'confirmed_by' => $user->id,
            ]);

            $wallet = $this->walletService->getOrCreateWallet($user);

            $this->walletService->credit(
                $wallet,
                (float) $income->amount,
                OpsWalletTransactionType::TRANSFER,
                $income,
                $user,
                $income->name
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('operational.confirmations.confirmed'),
                'data' => new OpsTransferConfirmationResource(
                    $opsTransferConfirmation->fresh()->load(['confirmable', 'confirmedBy'])
                ),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function reject(Request $request, OpsTransferConfirmation $opsTransferConfirmation)
    {
        $user = $request->user();
        $this->authorizeConfirmationAccess($opsTransferConfirmation, Role::MANDOR);

        if ($opsTransferConfirmation->status !== OpsTransferConfirmationStatus::PENDING) {
            return response()->json([
                'success' => false,
                'message' => __('operational.confirmations.already_processed'),
                'code' => 422,
            ], 422);
        }

        $opsTransferConfirmation->update([
            'status' => OpsTransferConfirmationStatus::REJECTED,
            'confirmed_at' => now(),
            'note' => $request->input('note'),
            'confirmed_by' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('operational.confirmations.rejected'),
            'data' => new OpsTransferConfirmationResource(
                $opsTransferConfirmation->fresh()->load(['confirmable', 'confirmedBy'])
            ),
        ]);
    }

    protected function authorizeConfirmationAccess(
        OpsTransferConfirmation $confirmation,
        ?Role $mandorOnly = null
    ): void {
        $user = request()->user();

        if ($mandorOnly === Role::MANDOR && $user->role !== Role::MANDOR) {
            abort(response()->json([
                'success' => false,
                'message' => 'You don\'t have permission to access this resource.',
                'code' => 403,
            ], 403));
        }

        if ($user->role !== Role::MANDOR) {
            return;
        }

        $confirmable = $confirmation->confirmable;

        if (!$confirmable instanceof OpsIncome || $confirmable->mandor_id !== $user->id) {
            abort(response()->json([
                'success' => false,
                'message' => 'You don\'t have permission to access this resource.',
                'code' => 403,
            ], 403));
        }
    }
}
