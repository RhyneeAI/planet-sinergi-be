<?php

namespace Database\Seeders;

use App\Enums\OpsExpenseType;
use App\Enums\OpsPaymentMethod;
use App\Enums\OpsSourceType;
use App\Enums\Role;
use App\Models\OpsExpense;
use App\Models\OpsIncome;
use App\Models\SubCompany;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OpsIncomeExpenseSeeder extends Seeder
{
    public function run(): void
    {
        $companyId = 1;
        $admin     = User::withoutGlobalScopes()->where('company_id', $companyId)->where('role', Role::ADMIN)->first();
        $mandors   = User::withoutGlobalScopes()->where('company_id', $companyId)->where('role', Role::MANDOR)->get();

        if (!$admin || $mandors->isEmpty()) {
            return;
        }

        $subCompanies = SubCompany::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('mandor_id', $mandors->pluck('id'))
            ->get()
            ->keyBy('mandor_id');

        $incomes  = array_merge(
            $this->internalIncomeData($admin->id, $companyId),
            $this->mandorIncomeData($mandors, $subCompanies, $admin->id, $companyId),
        );
        $expenses = array_merge(
            $this->internalExpenseData($admin->id, $companyId),
            $this->mandorExpenseData($mandors, $subCompanies, $admin->id, $companyId),
        );

        foreach ($incomes as $row) {
            OpsIncome::withoutGlobalScopes()->firstOrCreate(
                ['uuid' => $row['uuid']],
                $row
            );
        }

        foreach ($expenses as $row) {
            OpsExpense::withoutGlobalScopes()->firstOrCreate(
                ['uuid' => $row['uuid']],
                $row
            );
        }
    }

    protected function internalIncomeData(int $adminId, int $companyId): array
    {
        $now = now();

        return [
            [
                'uuid'           => (string) Str::uuid(),
                'name'           => 'Setoran Modal Awal',
                'amount'         => 50000000,
                'date'           => $now->copy()->subDays(30)->format('Y-m-d'),
                'payment_method' => OpsPaymentMethod::TRANSFER->value,
                'source_type'    => OpsSourceType::INTERNAL->value,
                'mandor_id'      => null,
                'sub_company_id' => null,
                'created_by'     => $adminId,
                'company_id'     => $companyId,
                'note'           => 'Setoran modal awal perusahaan',
                'proof_files'    => null,
            ],
            [
                'uuid'           => (string) Str::uuid(),
                'name'           => 'Pendapatan Jasa Konsultasi',
                'amount'         => 15000000,
                'date'           => $now->copy()->subDays(20)->format('Y-m-d'),
                'payment_method' => OpsPaymentMethod::TRANSFER->value,
                'source_type'    => OpsSourceType::INTERNAL->value,
                'mandor_id'      => null,
                'sub_company_id' => null,
                'created_by'     => $adminId,
                'company_id'     => $companyId,
                'note'           => 'Jasa konsultasi manajemen',
                'proof_files'    => null,
            ],
            [
                'uuid'           => (string) Str::uuid(),
                'name'           => 'Bunga Bank',
                'amount'         => 500000,
                'date'           => $now->copy()->subDays(10)->format('Y-m-d'),
                'payment_method' => OpsPaymentMethod::TRANSFER->value,
                'source_type'    => OpsSourceType::INTERNAL->value,
                'mandor_id'      => null,
                'sub_company_id' => null,
                'created_by'     => $adminId,
                'company_id'     => $companyId,
                'note'           => 'Bunga jasa giro',
                'proof_files'    => null,
            ],
            [
                'uuid'           => (string) Str::uuid(),
                'name'           => 'Pendapatan Lain-lain',
                'amount'         => 2000000,
                'date'           => $now->copy()->subDays(5)->format('Y-m-d'),
                'payment_method' => OpsPaymentMethod::CASH->value,
                'source_type'    => OpsSourceType::INTERNAL->value,
                'mandor_id'      => null,
                'sub_company_id' => null,
                'created_by'     => $adminId,
                'company_id'     => $companyId,
                'note'           => 'Penjualan aset tidak terpakai',
                'proof_files'    => null,
            ],
            [
                'uuid'           => (string) Str::uuid(),
                'name'           => 'Pendapatan Sewa',
                'amount'         => 3000000,
                'date'           => $now->copy()->subDays(2)->format('Y-m-d'),
                'payment_method' => OpsPaymentMethod::TRANSFER->value,
                'source_type'    => OpsSourceType::INTERNAL->value,
                'mandor_id'      => null,
                'sub_company_id' => null,
                'created_by'     => $adminId,
                'company_id'     => $companyId,
                'note'           => 'Sewa gedung lantai 2',
                'proof_files'    => null,
            ],
            [
                'uuid'           => (string) Str::uuid(),
                'name'           => 'Pendapatan Pelatihan',
                'amount'         => 4000000,
                'date'           => $now->format('Y-m-d'),
                'payment_method' => OpsPaymentMethod::TRANSFER->value,
                'source_type'    => OpsSourceType::INTERNAL->value,
                'mandor_id'      => null,
                'sub_company_id' => null,
                'created_by'     => $adminId,
                'company_id'     => $companyId,
                'note'           => 'Pelatihan SDM eksternal',
                'proof_files'    => null,
            ],
        ];
    }

    protected function internalExpenseData(int $adminId, int $companyId): array
    {
        $rows = [
            ['name' => 'Sewa Kantor',       'amount' => 10000000, 'days' => 28, 'note' => 'Sewa gedung bulan lalu'],
            ['name' => 'Listrik & Air',      'amount' => 2500000,  'days' => 25, 'note' => 'Tagihan utilitas'],
            ['name' => 'Gaji Karyawan',      'amount' => 20000000, 'days' => 15, 'note' => 'Penggajian bulan lalu'],
            ['name' => 'ATK & Operasional',  'amount' => 1500000,  'days' => 8,  'note' => 'Alat tulis kantor'],
            ['name' => 'Biaya Listrik',      'amount' => 3000000,  'days' => 3,  'note' => 'Tagihan listrik bulan ini'],
            ['name' => 'Biaya Internet',     'amount' => 1000000,  'days' => 0,  'note' => 'Langganan ISP'],
        ];

        return array_map(function ($r) use ($adminId, $companyId) {
            return [
                'uuid'           => (string) Str::uuid(),
                'name'           => $r['name'],
                'amount'         => $r['amount'],
                'date'           => now()->subDays($r['days'])->format('Y-m-d'),
                'payment_method' => OpsPaymentMethod::TRANSFER->value,
                'expense_type'   => OpsExpenseType::INTERNAL->value,
                'mandor_id'      => null,
                'sub_company_id' => null,
                'created_by'     => $adminId,
                'company_id'     => $companyId,
                'note'           => $r['note'],
                'proof_files'    => null,
            ];
        }, $rows);
    }

    protected function mandorIncomeData($mandors, $subCompanies, int $adminId, int $companyId): array
    {
        $now = now();
        $items = [];

        foreach ($mandors as $mandor) {
            $subCompany    = $subCompanies->get($mandor->id);
            $subCompanyId  = $subCompany?->id;
            $cabangName    = $subCompany?->name ?? 'cabang';

            $data = [
                ['name' => 'Hasil Penjualan Cabang', 'amount' => 8000000, 'days' => 14, 'note' => "Omzet penjualan {$cabangName}"],
                ['name' => 'Pendapatan Jasa Cabang', 'amount' => 3500000, 'days' => 7,  'note' => 'Jasa perbaikan'],
                ['name' => 'Pendapatan Harian',      'amount' => 1500000, 'days' => 1,  'note' => 'Setoran harian'],
            ];

            foreach ($data as $d) {
                $items[] = [
                    'uuid'           => (string) Str::uuid(),
                    'name'           => $d['name'],
                    'amount'         => $d['amount'],
                    'date'           => $now->copy()->subDays($d['days'])->format('Y-m-d'),
                    'payment_method' => rand(0, 1) ? OpsPaymentMethod::TRANSFER->value : OpsPaymentMethod::CASH->value,
                    'source_type'    => OpsSourceType::INTERNAL->value,
                    'mandor_id'      => $mandor->id,
                    'sub_company_id' => $subCompanyId,
                    'created_by'     => $adminId,
                    'company_id'     => $companyId,
                    'note'           => $d['note'],
                    'proof_files'    => null,
                ];
            }
        }

        return $items;
    }

    protected function mandorExpenseData($mandors, $subCompanies, int $adminId, int $companyId): array
    {
        $now = now();
        $items = [];

        foreach ($mandors as $mandor) {
            $subCompany   = $subCompanies->get($mandor->id);
            $subCompanyId = $subCompany?->id;
            $cabangName   = $subCompany?->name ?? 'cabang';

            $data = [
                ['name' => 'Beli Bahan Baku',  'amount' => 5000000, 'days' => 12, 'note' => "Pembelian bahan baku {$cabangName}"],
                ['name' => 'Biaya Transport',  'amount' => 750000,  'days' => 6,  'note' => 'Biaya pengiriman'],
                ['name' => 'Perawatan Alat',   'amount' => 2000000, 'days' => 1,  'note' => 'Service rutin peralatan'],
            ];

            foreach ($data as $d) {
                $items[] = [
                    'uuid'           => (string) Str::uuid(),
                    'name'           => $d['name'],
                    'amount'         => $d['amount'],
                    'date'           => $now->copy()->subDays($d['days'])->format('Y-m-d'),
                    'payment_method' => rand(0, 1) ? OpsPaymentMethod::TRANSFER->value : OpsPaymentMethod::CASH->value,
                    'expense_type'   => OpsExpenseType::INTERNAL->value,
                    'mandor_id'      => $mandor->id,
                    'sub_company_id' => $subCompanyId,
                    'created_by'     => $adminId,
                    'company_id'     => $companyId,
                    'note'           => $d['note'],
                    'proof_files'    => null,
                ];
            }
        }

        return $items;
    }
}
