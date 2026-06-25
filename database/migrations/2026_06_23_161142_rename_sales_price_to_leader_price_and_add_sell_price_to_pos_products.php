<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_products', function (Blueprint $table) {
            $table->double('leader_price', 15, 2)->nullable()->after('base_price');
            $table->double('sell_price', 15, 2)->nullable()->after('marketing_price');
        });

        DB::statement('UPDATE pos_products SET leader_price = sales_price');

        Schema::table('pos_products', function (Blueprint $table) {
            $table->dropColumn('sales_price');
        });
    }

    public function down(): void
    {
        Schema::table('pos_products', function (Blueprint $table) {
            $table->double('sales_price', 15, 2)->nullable()->after('base_price');
        });

        DB::statement('UPDATE pos_products SET sales_price = leader_price');

        Schema::table('pos_products', function (Blueprint $table) {
            $table->dropColumn(['leader_price', 'sell_price']);
        });
    }
};
