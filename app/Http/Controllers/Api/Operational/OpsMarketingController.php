<?php

namespace App\Http\Controllers\Api\Operational;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operational\OpsMarketingRequest;
use App\Http\Resources\Operational\OpsMarketingResource;
use App\Models\MarketingLeadMember;
use App\Models\PosSalesTransaction;
use App\Models\User;
use App\Http\Traits\DataTablesResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class OpsMarketingController extends Controller
{
    use DataTablesResponse;

    protected array $sortableColumns = ['name', 'phone', 'email', 'created_at'];

    public function index(Request $request)
    {
        $orderByKey = in_array($request->input('order_by_key', 'name'), $this->sortableColumns)
            ? $request->input('order_by_key', 'name')
            : 'name';
        $orderByValue = strtoupper($request->input('order_by_value', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

        $query = User::query()
            ->whereIn('role', Role::commissionMarketingValues())
            ->where('company_id', $request->user()->company_id);

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('leader_uuid')) {
            $leaderId = User::where('uuid', $request->leader_uuid)
                ->where('company_id', $request->user()->company_id)
                ->where('role', Role::MARKETING_LEAD)
                ->value('id');

            if (!$leaderId) {
                return response()->json([
                    'success' => false,
                    'message' => __('operational.marketings.validation.leader_not_found'),
                    'code' => 422,
                ], 422);
            }

            $query->whereHas('leaderUser', fn ($q) => $q->where('users.id', $leaderId));
        }

        if ($request->filled('search')) {
            $searchLower = strtolower($request->search);
            $query->where(function ($q) use ($searchLower) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%' . $searchLower . '%'])
                    ->orWhereRaw('LOWER(phone) LIKE ?', ['%' . $searchLower . '%'])
                    ->orWhereRaw('LOWER(email) LIKE ?', ['%' . $searchLower . '%']);
            });
        }

        $marketings = $query
            ->with(['leaderUser'])
            ->withCount('memberUsers')
            ->orderBy($orderByKey, $orderByValue)
            ->paginate($request->input('per_page', 15));

        return response()->json(
            $this->dataTablesResponse($request, $marketings, [
                'success' => true,
                'message' => __('operational.marketings.list'),
                'data'    => OpsMarketingResource::collection($marketings),
            ])
        );
    }

    public function store(OpsMarketingRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $role = Role::from($request->role);
            $phone = $this->generateUniquePhone($request->phone);
            $rawPassword = strtolower(str_replace(' ', '', $request->name)) . str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT);

            $marketing = User::create([
                'name' => $request->name,
                'phone' => $phone,
                'email' => $request->email,
                'password' => Hash::make($rawPassword),
                'address' => $request->address,
                'role' => $role,
                'is_active' => false,
                'company_id' => $request->user()->company_id,
                'created_by' => $request->user()->id,
            ]);

            if ($role === Role::MARKETING) {
                $this->syncLeaderAssignment($marketing, $request->leader_uuid);
            }

            return response()->json([
                'success' => true,
                'message' => __('operational.marketings.stored'),
                'data' => new OpsMarketingResource($marketing->load('leaderUser')),
            ], 201);
        });
    }

    public function show(User $user)
    {
        if ($response = $this->assertCommissionMarketing($user)) {
            return $response;
        }

        $user->load(['leaderUser', 'memberUsers']);
        $user->loadCount('memberUsers');

        return response()->json([
            'success' => true,
            'message' => __('operational.marketings.detail'),
            'data' => new OpsMarketingResource($user),
        ]);
    }

    public function update(OpsMarketingRequest $request, User $user)
    {
        if ($response = $this->assertCommissionMarketing($user)) {
            return $response;
        }

        return DB::transaction(function () use ($request, $user) {
            $data = array_filter([
                'name' => $request->input('name'),
                'phone' => $request->input('phone'),
                'email' => $request->input('email'),
                'address' => $request->input('address'),
            ], fn ($value) => !is_null($value));

            if ($request->filled('password')) {
                $data['password'] = Hash::make($request->password);
            }

            if (!empty($data)) {
                $user->update($data);
            }

            if ($user->role === Role::MARKETING && $request->has('leader_uuid')) {
                $this->syncLeaderAssignment($user, $request->leader_uuid);
            }

            return response()->json([
                'success' => true,
                'message' => __('operational.marketings.updated'),
                'data' => new OpsMarketingResource($user->fresh()->load(['leaderUser', 'memberUsers'])),
            ]);
        });
    }

    public function destroy(User $user)
    {
        if ($response = $this->assertCommissionMarketing($user)) {
            return $response;
        }

        if ($user->role === Role::MARKETING_LEAD) {
            $hasMembers = MarketingLeadMember::where('leader_id', $user->id)->exists();
            if ($hasMembers) {
                return response()->json([
                    'success' => false,
                    'message' => __('operational.marketings.has_members'),
                    'code' => 422,
                ], 422);
            }
        }

        if (PosSalesTransaction::where('marketing_id', $user->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => __('operational.marketings.has_transactions'),
                'code' => 422,
            ], 422);
        }

        $user->leaders()->delete();
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => __('operational.marketings.deleted'),
        ]);
    }

    protected function syncLeaderAssignment(User $marketing, ?string $leaderUuid): void
    {
        $marketing->leaders()->delete();

        if (!$leaderUuid) {
            return;
        }

        $leaderId = User::where('uuid', $leaderUuid)
            ->where('company_id', $marketing->company_id)
            ->where('role', Role::MARKETING_LEAD)
            ->value('id');

        if (!$leaderId) {
            return;
        }

        MarketingLeadMember::create([
            'marketing_id' => $marketing->id,
            'leader_id' => $leaderId,
        ]);
    }

    protected function generateUniquePhone(string $base): string
    {
        $phone = $base;
        $counter = 1;

        while (User::where('phone', $phone)->exists()) {
            $phone = $base . $counter;
            $counter++;
        }

        return $phone;
    }

    protected function assertCommissionMarketing(User $user): ?JsonResponse
    {
        if (!$user->role?->isCommissionMarketing()) {
            return response()->json([
                'success' => false,
                'message' => __('operational.marketings.not_found'),
                'code' => 404,
            ], 404);
        }

        return null;
    }
}
