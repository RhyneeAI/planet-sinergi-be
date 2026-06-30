<?php

namespace App\Http\Controllers\Api\Operational;

use App\Enums\OpsSourceType;
use App\Enums\OpsTransferConfirmationStatus;
use App\Enums\OpsWalletTransactionType;
use App\Enums\Role;
use App\Http\Controllers\Api\Operational\ReturnsEmptyShowResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operational\OpsTransferConfirmationRequest;
use App\Http\Resources\Operational\OpsTransferConfirmationResource;
use App\Models\OpsExpense;
use App\Models\OpsIncome;
use App\Models\OpsTransferConfirmation;
use App\Services\Operational\OpsFileService;
use App\Services\Operational\OpsTransferConfirmationAccess;
use App\Services\Operational\OpsWalletService;
use App\Http\Traits\DataTablesResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OpsTransferConfirmationController extends Controller
{
    use DataTablesResponse;
    use ReturnsEmptyShowResponse;

    public function __construct(
        protected OpsFileService $fileService,
        protected OpsWalletService $walletService,
        protected OpsTransferConfirmationAccess $transferAccess,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $confirmations = OpsTransferConfirmation::with(['confirmable.subCompany', 'confirmable.mandor', 'confirmedBy'])
            ->when(
                in_array($user->role, [Role::MANDOR, Role::KEPALA_MANDOR]),
                fn (Builder $query) => $this->transferAccess->scopeForMandor($query, $user)
            )
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->sub_company_uuid, function ($query, $uuid) {
                $query->whereHasMorph(
                    'confirmable',
                    [OpsExpense::class, OpsIncome::class],
                    fn($q) => $q->whereHas('subCompany', fn($sub) => $sub->where('uuid', $uuid))
                );
            })
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 15));

        return response()->json(
            $this->dataTablesResponse($request, $confirmations, [
                'success' => true,
                'message' => __('operational.confirmations.list'),
                'data' => OpsTransferConfirmationResource::collection($confirmations),
            ])
        );
    }

    public function show(Request $request, string $uuid)
    {
        $opsTransferConfirmation = OpsTransferConfirmation::where('uuid', $uuid)->first();

        if (!$opsTransferConfirmation) {
            return $this->emptyShowResponse(__('operational.confirmations.detail'));
        }

        $this->authorizeConfirmationAccess($opsTransferConfirmation);

        return response()->json([
            'success' => true,
            'message' => __('operational.confirmations.detail'),
            'data' => new OpsTransferConfirmationResource(
                $opsTransferConfirmation->load(['confirmable.mandor', 'confirmable.subCompany', 'confirmable.createdBy', 'confirmedBy'])
            ),
        ]);
    }

    public function confirm(
        OpsTransferConfirmationRequest $request,
        OpsTransferConfirmation $opsTransferConfirmation
    ) {
        $user = $request->user();
        $this->authorizeConfirmationAccess($opsTransferConfirmation, $user->role);

        if ($opsTransferConfirmation->status !== OpsTransferConfirmationStatus::PENDING) {
            return response()->json([
                'success' => false,
                'message' => __('operational.confirmations.already_processed'),
                'code' => 422,
            ], 422);
        }

        $confirmedAmount = round((float) $request->confirmed_amount, 2);

        DB::beginTransaction();

        try {
            $opsTransferConfirmation->loadMissing('confirmable');
            $confirmable = $opsTransferConfirmation->confirmable;

            if (!$confirmable) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => __('operational.confirmations.income_not_found'),
                    'code' => 422,
                ], 422);
            }

            $subCompany = $confirmable->subCompany;

            if (!$subCompany) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => __('operational.validation.sub_company_uuid_not_found'),
                    'code' => 422,
                ], 422);
            }

            if ($confirmable instanceof OpsExpense) {
                $income = OpsIncome::create([
                    'name' => $confirmable->name,
                    'amount' => $confirmedAmount,
                    'date' => $confirmable->date,
                    'payment_method' => $confirmable->payment_method,
                    'proof_files' => $confirmable->proof_files,
                    'note' => $confirmable->note,
                    'source_type' => OpsSourceType::MANDOR,
                    'mandor_id' => $confirmable->mandor_id,
                    'sub_company_id' => $confirmable->sub_company_id,
                    'created_by' => $user->id,
                    'company_id' => $confirmable->company_id,
                ]);

                $confirmable->update(['transfer_income_id' => $income->id]);
            } elseif ($confirmable instanceof OpsIncome) {
                $income = $confirmable;
                $income->update(['amount' => $confirmedAmount]);
            } else {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => __('operational.confirmations.income_not_found'),
                    'code' => 422,
                ], 422);
            }

            $opsTransferConfirmation->update([
                'status' => OpsTransferConfirmationStatus::CONFIRMED,
                'confirmed_amount' => $confirmedAmount,
                'mandor_proof_files' => $this->storeMandorProofFiles($request),
                'confirmed_at' => now(),
                'note' => $request->note,
                'confirmed_by' => $user->id,
            ]);

            $wallet = $this->walletService->getOrCreateWallet($user, $subCompany);

            $this->walletService->credit(
                $wallet,
                $confirmedAmount,
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
                    $opsTransferConfirmation->fresh()->load(['confirmable' => fn ($q) => $q->withTrashed(), 'confirmable.subCompany', 'confirmable.mandor', 'confirmedBy'])
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
        $this->authorizeConfirmationAccess($opsTransferConfirmation, $user->role);

        if ($opsTransferConfirmation->status !== OpsTransferConfirmationStatus::PENDING) {
            return response()->json([
                'success' => false,
                'message' => __('operational.confirmations.already_processed'),
                'code' => 422,
            ], 422);
        }

        DB::transaction(function () use ($opsTransferConfirmation, $request, $user) {
            $opsTransferConfirmation->loadMissing('confirmable');
            $confirmable = $opsTransferConfirmation->confirmable;

            if ($confirmable instanceof OpsExpense) {
                $confirmable->delete();
            } elseif ($confirmable instanceof OpsIncome) {
                $confirmable->delete();
                OpsExpense::where('transfer_income_id', $confirmable->id)->delete();
            }

            $opsTransferConfirmation->update([
                'status' => OpsTransferConfirmationStatus::REJECTED,
                'confirmed_at' => now(),
                'note' => $request->input('note'),
                'confirmed_by' => $user->id,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => __('operational.confirmations.rejected'),
            'data' => new OpsTransferConfirmationResource(
                $opsTransferConfirmation->fresh()->load(['confirmable' => fn ($q) => $q->withTrashed(), 'confirmable.subCompany', 'confirmable.mandor', 'confirmedBy'])
            ),
        ]);
    }

    protected function storeMandorProofFiles(Request $request): array
    {
        $files = $request->file('mandor_proof_files');

        return $this->fileService->storeProofs(is_array($files) ? $files : [$files], 'transfer', 'mandor');
    }

    protected function authorizeConfirmationAccess(
        OpsTransferConfirmation $confirmation,
        ?Role $mandorOnly = null
    ): void {
        $user = request()->user();
        $isMandorOrKepala = in_array($user->role, [Role::MANDOR, Role::KEPALA_MANDOR]);

        if ($mandorOnly !== null && !$isMandorOrKepala) {
            abort(response()->json([
                'success' => false,
                'message' => __('operational.confirmations.not_accessible'),
                'code' => 403,
            ], 403));
        }

        if (!$isMandorOrKepala) {
            return;
        }

        $confirmation->loadMissing('confirmable');
        $confirmable = $confirmation->confirmable;

        $mandorId = $confirmable?->mandor_id;
        $subCompanyMandorId = $confirmable?->subCompany?->mandor_id;

        $canAccess = $mandorId && (int) $mandorId === (int) $user->id
            || $subCompanyMandorId && (int) $subCompanyMandorId === (int) $user->id;

        if (!$confirmable || !$canAccess) {
            abort(response()->json([
                'success' => false,
                'message' => __('operational.confirmations.not_accessible'),
                'code' => 403,
            ], 403));
        }
    }
}
