<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // Inti: company + semua role + master POS minimal
            CompanySeeder::class,
            UserSeeder::class,
            UnitSeeder::class,
            CategorySeeder::class,
            SupplierSeeder::class,
            CustomerTypeSeeder::class,
            CustomerSeeder::class,
            ProductSeeder::class,

            // Operasional: config + sub-company otomatis per mandor
            OpsConfigurationSeeder::class,
            SubCompanySeeder::class,
            AbsJabatanSeeder::class,
            AbsShiftSeeder::class,
            AbsEmployeeProfileSeeder::class,

            // Operasional: dummy incomes & expenses untuk testing report
            OpsIncomeExpenseSeeder::class,

            // Laporan: company terpisah, data transaksi fokus
            MarketingCommissionReportSeeder::class,
            SalesRevenueReportSeeder::class,
        ]);
    }
}
