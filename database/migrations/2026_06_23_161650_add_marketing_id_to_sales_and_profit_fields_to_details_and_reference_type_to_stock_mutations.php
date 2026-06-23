<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_sales_transactions', function (Blueprint $table) {
            $table->foreignId('marketing_id')->nullable()->constrained('users')->onDelete('restrict');
            $table->index(['marketing_id', 'company_id']);
        });

        Schema::table('pos_sales_details', function (Blueprint $table) {
            $table->double('company_profit', 15, 2)->default(0)->after('marketing_price');
            $table->double('lead_profit', 15, 2)->default(0)->after('company_profit');
            $table->double('marketing_profit', 15, 2)->default(0)->after('lead_profit');
        });

        Schema::table('pos_stock_mutations', function (Blueprint $table) {
            $table->string('reference_type')->nullable()->after('reference_id');
        });
    }

    public function down(): void
    {
        Schema::table('pos_sales_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('marketing_id');
        });

        Schema::table('pos_sales_details', function (Blueprint $table) {
            $table->dropColumn(['company_profit', 'lead_profit', 'marketing_profit']);
        });

        Schema::table('pos_stock_mutations', function (Blueprint $table) {
            $table->dropColumn('reference_type');
        });
    }
};
