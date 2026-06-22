<?php

namespace App\Http\Controllers\Api\Pos;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\PosMarketingRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PosMarketingController extends Controller
{
    protected array $sortableColumns = ['name', 'phone', 'email', 'created_at']; // ← username → phone

    public function index(Request $request)
    {
        $orderByKey   = in_array($request->input('order_by_key', 'name'), $this->sortableColumns)
                            ? $request->input('order_by_key', 'name')
                            : 'name';
        $orderByValue = strtoupper($request->input('order_by_value', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

        $marketings = User::where('role', ROLE::MARKETING)
            ->when($request->search, function ($query, $search) {
                $searchLower = strtolower($search);
                $query->where(function ($q) use ($searchLower) {
                    $q->whereRaw('LOWER(name) LIKE ?', ['%' . $searchLower . '%'])
                      ->orWhereRaw('LOWER(phone) LIKE ?', ['%' . $searchLower . '%']) // ← username → phone
                      ->orWhereRaw('LOWER(email) LIKE ?', ['%' . $searchLower . '%']);
                });
            })
            ->orderBy($orderByKey, $orderByValue)
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => __('pos.marketings.list'),
            'data'    => UserResource::collection($marketings),
        ]);
    }

    public function store(PosMarketingRequest $request)
    {
        // Generate phone (bisa dari request atau auto-generate)
        $basePhone = $request->phone ?? '08' . rand(100000000, 999999999);
        $phone = $this->generateUniquePhone($basePhone);

        $randomDigits = str_pad(rand(0, 999), 3, '0', STR_PAD_LEFT);
        $rawPassword  = strtolower(str_replace(' ', '', $request->name)) . $randomDigits;

        $marketing = User::create([
            'name'       => $request->name,
            'phone'      => $phone,
            'email'      => $request->email,
            'password'   => Hash::make($rawPassword),
            'address'    => $request->address,
            'role'       => Role::MARKETING,
            'company_id' => $request->user()->company_id,
        ]);

        app(\App\Services\Absence\AbsEmployeeProfileService::class)->syncFromRequest($marketing, $request->only([
            'jabatan_uuid', 'sub_company_uuid', 'shift_uuid',
        ]));

        return response()->json([
            'success' => true,
            'message' => __('pos.marketings.stored'),
            'data'    => new UserResource($marketing),
            'credentials' => [
                'phone'    => $phone, // ← username → phone
                'password' => $rawPassword,
            ],
        ], 201);
    }

    private function generateUniquePhone(string $base): string
    {
        $phone = $base;
        $counter = 1;

        while (User::where('phone', $phone)->exists()) {
            $phone = $base . $counter;
            $counter++;
        }

        return $phone;
    }

    public function show(User $marketing)
    {
        return response()->json([
            'success' => true,
            'message' => __('pos.marketings.detail'),
            'data'    => new UserResource($marketing),
        ]);
    }

    public function update(PosMarketingRequest $request, User $marketing)
    {
        $data = $request->only(['name', 'phone', 'email', 'address']); // ← username → phone

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $marketing->update($data);

        app(\App\Services\Absence\AbsEmployeeProfileService::class)->syncFromRequest($marketing, [
            'jabatan_uuid' => $request->input('jabatan_uuid'),
            'sub_company_uuid' => $request->input('sub_company_uuid'),
            'shift_uuid' => $request->input('shift_uuid'),
        ]);

        return response()->json([
            'success' => true,
            'message' => __('pos.marketings.updated'),
            'data'    => new UserResource($marketing),
        ]);
    }

    public function destroy(User $marketing)
    {
        $hasProducts     = $marketing->marketingProducts()->exists();
        $hasTransactions = $marketing->salesTransactions()->exists();

        if ($hasProducts || $hasTransactions) {
            return response()->json([
                'success' => false,
                'message' => __('pos.marketings.has_relations'),
                'code'    => 422,
            ], 422);
        }

        $marketing->delete();

        return response()->json([
            'success' => true,
            'message' => __('pos.marketings.deleted'),
        ]);
    }
}
