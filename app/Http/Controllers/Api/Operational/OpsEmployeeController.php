<?php

namespace App\Http\Controllers\Api\Operational;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operational\OpsEmployeeRequest;
use App\Http\Resources\Operational\OpsEmployeeResource;
use App\Models\User;
use App\Services\Absence\AbsEmployeeProfileService;
use Illuminate\Http\Request;
use App\Http\Traits\DataTablesResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class OpsEmployeeController extends Controller
{
    use DataTablesResponse;

    public function __construct(
        protected AbsEmployeeProfileService $employeeProfileService,
    ) {}

    public function index(Request $request)
    {
        $orderByKey = $request->input('order_by', 'name');
        $orderByValue = $request->input('order_by_value', 'ASC');

        $employees = User::with(['absEmployeeProfile.jabatan', 'absEmployeeProfile.subCompany', 'absEmployeeProfile.shift'])
            ->when($request->role, fn($q, $role) => $q->where('role', $role))
            ->when(
                $request->sub_company_uuid,
                fn($q, $uuid) =>
                $q->whereHas('absEmployeeProfile.subCompany', fn($sc) => $sc->where('uuid', $uuid))
            )
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->search, function ($query, $search) {
                $term = '%' . strtolower($search) . '%';
                $query->where(function ($inner) use ($term) {
                    $inner->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(phone) LIKE ?', [$term]);
                });
            })
            ->orderBy($orderByKey, $orderByValue)
            ->paginate($request->input('per_page', 15));

        return response()->json(
            $this->dataTablesResponse($request, $employees, [
                'success' => true,
                'message' => __('operational.employees.list'),
                'data' => OpsEmployeeResource::collection($employees),
            ])
        );
    }

    public function store(OpsEmployeeRequest $request)
    {
        DB::beginTransaction();

        try {
            $user = User::create([
                'name' => $request->name,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'role' => $request->role,
                'is_active' => true,
                'company_id' => $request->user()->company_id,
                'created_by' => $request->user()->id,
            ]);

            $this->employeeProfileService->syncFromRequest($user, $request->validated());

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('operational.employees.stored'),
                'data' => new OpsEmployeeResource(
                    $user->load(['absEmployeeProfile.jabatan', 'absEmployeeProfile.subCompany', 'absEmployeeProfile.shift'])
                ),
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function show(User $user)
    {
        return response()->json([
            'success' => true,
            'message' => __('operational.employees.detail'),
            'data' => new OpsEmployeeResource(
                $user->load(['absEmployeeProfile.jabatan', 'absEmployeeProfile.subCompany', 'absEmployeeProfile.shift'])
            ),
        ]);
    }

    public function update(OpsEmployeeRequest $request, User $user)
    {
        DB::beginTransaction();

        try {
            $updateData = array_filter([
                'name' => $request->input('name'),
                'phone' => $request->input('phone'),
                'role' => $request->input('role'),
            ], fn($value) => !is_null($value));

            if ($request->filled('password')) {
                $updateData['password'] = Hash::make($request->password);
            }

            if ($request->has('is_active')) {
                $updateData['is_active'] = $request->boolean('is_active');
            }

            if (!empty($updateData)) {
                $user->update($updateData);
            }

            $this->employeeProfileService->syncFromRequest($user->fresh(), $request->validated());

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('operational.employees.updated'),
                'data' => new OpsEmployeeResource(
                    $user->fresh()->load(['absEmployeeProfile.jabatan', 'absEmployeeProfile.subCompany', 'absEmployeeProfile.shift'])
                ),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function destroy(User $user)
    {
        $user->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => __('operational.employees.deactivated'),
        ]);
    }

    public function resetPassword(Request $request, User $user)
    {
        $request->validate([
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'success' => true,
            'message' => __('operational.employees.password_reset'),
        ]);
    }
}
