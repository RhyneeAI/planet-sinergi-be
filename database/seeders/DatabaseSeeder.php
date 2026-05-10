<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\Test\ProductionSeeder;
use Database\Seeders\Test\StagingSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CompanySeeder::class,
            UserSeeder::class,
            UnitSeeder::class,
            CategorySeeder::class,
            SupplierSeeder::class,
            CustomerTypeSeeder::class,
            CustomerSeeder::class,
            ProductSeeder::class,
            SalesTransactionSeeder::class,
            PurchaseTransactionSeeder::class,

            MarketingCommissionReportSeeder::class
        ]);

        if (app()->environment('local', 'staging')) {
            $this->call(StagingSeeder::class);
            $this->info('Staging seeder dijalankan.');
        }

        if (app()->environment('production')) {
            $this->call(ProductionSeeder::class);
            $this->info('Production seeder dijalankan.');
        }
    }
}
