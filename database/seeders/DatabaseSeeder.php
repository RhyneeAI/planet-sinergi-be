<?php

namespace Database\Seeders;

use Database\Seeders\Test\ProductionSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->call([
                ProductionSeeder::class,
            ]);

            return;
        }

        $this->call([
            CompanySeeder::class,
            UserSeeder::class,
            PosUnitSeeder::class,
            PosCategorySeeder::class,
            PosSupplierSeeder::class,
            PosCustomerTypeSeeder::class,
            PosCustomerSeeder::class,
            PosProductSeeder::class,
            PosSalesDemoSeeder::class,

            OpsConfigurationSeeder::class,
            SubCompanySeeder::class,
            AbsJabatanSeeder::class,
            AbsShiftSeeder::class,
            AbsEmployeeProfileSeeder::class,
            CustomConfigurationSeeder::class,

            OpsIncomeExpenseSeeder::class,

            PosMarketingCommissionReportSeeder::class,
            PosSalesRevenueReportSeeder::class,
        ]);
    }
}
