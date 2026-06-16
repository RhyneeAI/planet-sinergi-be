<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Company;
use App\Models\OpsConfiguration;
use App\Models\SubCompany;
use App\Models\User;
use App\Services\Operational\OpsWalletService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

class SubCompanyService
{
    public const KEY_MAX_SUB_COMPANIES_PER_MANDOR = 'max_sub_companies_per_mandor';

    public function __construct(
        protected OpsWalletService $walletService,
    ) {}

    public function maxSubCompaniesPerMandor(int $companyId): int
    {
        $configured = OpsConfiguration::where('company_id', $companyId)
            ->where('key', self::KEY_MAX_SUB_COMPANIES_PER_MANDOR)
            ->value('value');

        if ($configured !== null) {
            return max(1, (int) $configured);
        }

        return (int) config('operational.max_sub_companies_per_mandor', 10);
    }

    public function countForMandor(int $mandorId): int
    {
        return SubCompany::where('mandor_id', $mandorId)->count();
    }

    public function createDefaultForMandor(User $mandor, ?User $createdBy = null): SubCompany
    {
        if ($mandor->role !== Role::MANDOR) {
            throw new \InvalidArgumentException('User must have MANDOR role.');
        }

        $existing = SubCompany::where('mandor_id', $mandor->id)->first();
        if ($existing) {
            return $existing;
        }

        $limit = $this->maxSubCompaniesPerMandor($mandor->company_id);
        if ($this->countForMandor($mandor->id) >= $limit) {
            throw new \RuntimeException(__('operational.sub_companies.limit_reached', [
                'limit' => $limit,
            ]));
        }

        $company = Company::findOrFail($mandor->company_id);

        $subCompany = SubCompany::create([
            'name' => $company->name,
            'code' => $this->generateUniqueCode($company),
            'address' => $company->address,
            'is_active' => true,
            'mandor_id' => $mandor->id,
            'company_id' => $company->id,
            'created_by' => $createdBy?->id,
        ]);

        $this->walletService->getOrCreateWallet($mandor, $subCompany);

        return $subCompany;
    }

    public function ensureDefaultForMandor(User $mandor, ?User $createdBy = null): SubCompany
    {
        return $this->createDefaultForMandor($mandor, $createdBy);
    }

    protected function generateUniqueCode(Company $company): string
    {
        $sequence = SubCompany::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->count() + 1;

        $code = $company->code . '-' . str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);

        while (
            SubCompany::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('code', $code)
                ->exists()
        ) {
            $sequence++;
            $code = $company->code . '-' . str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);
        }

        return $code;
    }

    public function resolveForMandor(string $uuid, User $mandor): SubCompany
    {
        $subCompany = SubCompany::where('uuid', $uuid)
            ->where('mandor_id', $mandor->id)
            ->where('is_active', true)
            ->first();

        if (!$subCompany) {
            throw ValidationException::withMessages([
                'sub_company_uuid' => [__('operational.validation.sub_company_uuid_not_found')],
            ]);
        }

        return $subCompany;
    }

    public function resolveForMandorRequest(?string $uuid, User $mandor): SubCompany
    {
        if ($uuid) {
            return $this->resolveForMandor($uuid, $mandor);
        }

        $branches = SubCompany::where('mandor_id', $mandor->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        if ($branches->count() === 1) {
            return $branches->first();
        }

        if ($branches->isEmpty()) {
            throw ValidationException::withMessages([
                'sub_company_uuid' => [__('operational.validation.sub_company_not_assigned')],
            ]);
        }

        throw ValidationException::withMessages([
            'sub_company_uuid' => [__('operational.validation.sub_company_uuid_required_multi')],
        ]);
    }

    public function provisionForNewMandor(
        User $mandor,
        ?string $subCompanyUuid,
        ?string $subCompanyName,
        ?string $subCompanyCode,
        ?User $createdBy = null,
    ): SubCompany {
        if ($mandor->role !== Role::MANDOR) {
            throw new \InvalidArgumentException('User must have MANDOR role.');
        }

        if ($subCompanyUuid) {
            return $this->assignExistingToMandor($subCompanyUuid, $mandor, $mandor->company_id, $createdBy);
        }

        if ($subCompanyName) {
            return $this->createNamedForMandor($mandor, $subCompanyName, $subCompanyCode, $createdBy);
        }

        throw ValidationException::withMessages([
            'sub_company_uuid' => [__('operational.validation.sub_company_branch_required')],
            'sub_company_name' => [__('operational.validation.sub_company_branch_required')],
        ]);
    }

    public function assignExistingToMandor(
        string $subCompanyUuid,
        User $mandor,
        int $companyId,
        ?User $createdBy = null,
    ): SubCompany {
        $subCompany = SubCompany::where('uuid', $subCompanyUuid)
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->first();

        if (!$subCompany) {
            throw ValidationException::withMessages([
                'sub_company_uuid' => [__('operational.validation.sub_company_uuid_not_found')],
            ]);
        }

        if ($subCompany->mandor_id !== $mandor->id) {
            $currentMandor = User::find($subCompany->mandor_id);

            if ($currentMandor && $currentMandor->is_active) {
                throw ValidationException::withMessages([
                    'sub_company_uuid' => [__('operational.validation.sub_company_already_assigned')],
                ]);
            }

            $limit = $this->maxSubCompaniesPerMandor($companyId);
            if ($this->countForMandor($mandor->id) >= $limit) {
                throw ValidationException::withMessages([
                    'sub_company_uuid' => [__('operational.sub_companies.limit_reached', ['limit' => $limit])],
                ]);
            }

            $subCompany->update([
                'mandor_id' => $mandor->id,
                'created_by' => $createdBy?->id ?? $subCompany->created_by,
            ]);
        }

        $this->walletService->getOrCreateWallet($mandor, $subCompany->fresh());

        return $subCompany->fresh();
    }

    public function createNamedForMandor(
        User $mandor,
        string $name,
        ?string $code,
        ?User $createdBy = null,
    ): SubCompany {
        $limit = $this->maxSubCompaniesPerMandor($mandor->company_id);
        if ($this->countForMandor($mandor->id) >= $limit) {
            throw ValidationException::withMessages([
                'sub_company_name' => [__('operational.sub_companies.limit_reached', ['limit' => $limit])],
            ]);
        }

        $company = Company::findOrFail($mandor->company_id);
        $resolvedCode = $code ?: $this->generateUniqueCode($company);

        if (
            SubCompany::where('company_id', $company->id)
                ->where('code', $resolvedCode)
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'sub_company_code' => [__('operational.validation.sub_company_code_unique')],
            ]);
        }

        $subCompany = SubCompany::create([
            'name' => $name,
            'code' => $resolvedCode,
            'address' => $mandor->address,
            'is_active' => true,
            'mandor_id' => $mandor->id,
            'company_id' => $company->id,
            'created_by' => $createdBy?->id,
        ]);

        $this->walletService->getOrCreateWallet($mandor, $subCompany);

        return $subCompany;
    }

    public function resolveForAdmin(string $uuid, int $companyId, ?int $mandorId = null): SubCompany
    {
        return SubCompany::where('uuid', $uuid)
            ->where('company_id', $companyId)
            ->when($mandorId, fn ($query) => $query->where('mandor_id', $mandorId))
            ->where('is_active', true)
            ->firstOrFail();
    }

    public function resolveMandor(string $mandorUuid, int $companyId): User
    {
        $mandor = User::where('uuid', $mandorUuid)
            ->where('company_id', $companyId)
            ->where('role', Role::MANDOR)
            ->where('is_active', true)
            ->first();

        if (!$mandor) {
            throw new ModelNotFoundException(__('operational.validation.mandor_uuid_not_found'));
        }

        return $mandor;
    }
}
