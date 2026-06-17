<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Api\Operational\ReturnsEmptyShowResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operational\SubCompanyStoreRequest;
use App\Http\Requests\Operational\SubCompanyUpdateRequest;
use App\Http\Resources\Operational\OpsMandorResource;
use App\Http\Resources\SubCompanyResource;
use App\Models\SubCompany;
use App\Models\User;
use App\Services\SubCompanyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubCompanyController extends Controller
{
    use ReturnsEmptyShowResponse;

    public function __construct(
        protected SubCompanyService $subCompanyService,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $subCompanies = SubCompany::with(['mandor', 'createdBy', 'wallet'])
            ->when($user->role === Role::MANDOR, fn ($query) => $query->where('mandor_id', $user->id))
            ->when(
                $request->mandor_uuid && $user->role !== Role::MANDOR,
                fn ($query) => $query->whereHas(
                    'mandor',
                    fn ($mandorQuery) => $mandorQuery->where('uuid', $request->mandor_uuid)
                )
            )
            ->when($request->has('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->when($request->search, function ($query, $search) {
                $query->where(function ($innerQuery) use ($search) {
                    $term = '%' . strtolower($search) . '%';
                    $innerQuery->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(code) LIKE ?', [$term]);
                });
            })
            ->orderBy('name')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => __('operational.sub_companies.list'),
            'data' => SubCompanyResource::collection($subCompanies),
        ]);
    }

    public function store(SubCompanyStoreRequest $request)
    {
        DB::beginTransaction();

        try {
            $result = $this->subCompanyService->createMandorWithSubCompany(
                $request->user(),
                $request->input('mandor'),
                $request->input('sub_company'),
            );

            DB::commit();

            $mandor = $result['mandor']->load(['subCompanies.wallet']);
            $subCompany = $result['subCompany']->load(['mandor', 'wallet']);

            return response()->json([
                'success' => true,
                'message' => __('operational.sub_companies.stored'),
                'data' => [
                    'sub_company' => new SubCompanyResource($subCompany),
                    'mandor' => new OpsMandorResource($mandor),
                    'credentials' => [
                        'phone' => $mandor->phone,
                        'username' => strtolower(preg_replace('/\s+/', '', $mandor->name)),
                        'password' => $result['rawPassword'],
                    ],
                ],
            ], 201);
        } catch (\Throwable $e) {
            User::$skipSubCompanyAutoCreate = false;
            DB::rollBack();
            throw $e;
        }
    }

    public function show(Request $request, string $uuid)
    {
        $subCompany = SubCompany::where('uuid', $uuid)->first();

        if (!$subCompany) {
            return $this->emptyShowResponse(__('operational.sub_companies.detail'));
        }

        $this->authorizeSubCompanyAccess($subCompany);

        return response()->json([
            'success' => true,
            'message' => __('operational.sub_companies.detail'),
            'data' => new SubCompanyResource(
                $subCompany->load(['mandor', 'createdBy', 'wallet'])
            ),
        ]);
    }

    public function update(SubCompanyUpdateRequest $request, string $uuid)
    {
        $subCompany = SubCompany::where('uuid', $uuid)->first();

        if (!$subCompany) {
            return response()->json([
                'success' => false,
                'message' => __('operational.validation.sub_company_uuid_not_found'),
                'code' => 404,
            ], 404);
        }

        $subCompany = $this->subCompanyService->updateForAdmin(
            $subCompany,
            $request->validated(),
            $request->user(),
        );

        return response()->json([
            'success' => true,
            'message' => __('operational.sub_companies.updated'),
            'data' => new SubCompanyResource(
                $subCompany->load(['mandor', 'createdBy', 'wallet'])
            ),
        ]);
    }

    public function destroy(Request $request, string $uuid)
    {
        $subCompany = SubCompany::where('uuid', $uuid)->first();

        if (!$subCompany) {
            return response()->json([
                'success' => false,
                'message' => __('operational.validation.sub_company_uuid_not_found'),
                'code' => 404,
            ], 404);
        }

        $this->subCompanyService->deleteForAdmin($subCompany);

        return response()->json([
            'success' => true,
            'message' => __('operational.sub_companies.deleted'),
        ]);
    }

    protected function authorizeSubCompanyAccess(SubCompany $subCompany): void
    {
        $user = request()->user();

        if ($user->role === Role::MANDOR && $subCompany->mandor_id !== $user->id) {
            abort(response()->json([
                'success' => false,
                'message' => 'You don\'t have permission to access this resource.',
                'code' => 403,
            ], 403));
        }
    }
}
