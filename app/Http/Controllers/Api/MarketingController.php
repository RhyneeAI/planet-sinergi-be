<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MarketingRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class MarketingController extends Controller
{
    protected array $sortableColumns = ['name', 'username', 'email', 'created_at'];

    public function index(Request $request)
    {
        $orderByKey   = in_array($request->input('order_by_key', 'name'), $this->sortableColumns)
                            ? $request->input('order_by_key', 'name')
                            : 'name';
        $orderByValue = strtoupper($request->input('order_by_value', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

        $marketings = User::where('role', 'marketing')
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('username', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy($orderByKey, $orderByValue)
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => __('marketings.list'),
            'data'    => UserResource::collection($marketings),
        ]);
    }

    public function store(MarketingRequest $request)
    {
        $marketing = User::create([
            'name'       => $request->name,
            'username'   => $request->username,
            'email'      => $request->email,
            'password'   => Hash::make($request->password),
            'address'    => $request->address,
            'phone'      => $request->phone,
            'role'       => 'marketing',
            'company_id' => $request->user()->company_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('marketings.stored'),
            'data'    => new UserResource($marketing),
        ], 201);
    }

    public function show(User $user)
    {
        return response()->json([
            'success' => true,
            'message' => __('marketings.detail'),
            'data'    => new UserResource($user),
        ]);
    }

    public function update(MarketingRequest $request, User $user)
    {
        $data = $request->only(['name', 'username', 'email', 'address', 'phone']);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return response()->json([
            'success' => true,
            'message' => __('marketings.updated'),
            'data'    => new UserResource($user),
        ]);
    }

    public function destroy(User $user)
    {
        $hasProducts     = $user->marketingProducts()->exists();
        $hasTransactions = $user->salesTransactions()->exists();

        if ($hasProducts || $hasTransactions) {
            return response()->json([
                'success' => false,
                'message' => __('marketings.has_relations'),
                'code'    => 422,
            ], 422);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => __('marketings.deleted'),
        ]);
    }
}