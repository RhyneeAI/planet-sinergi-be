<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_sales_installment_plans', function (Blueprint $table) {
            $table->double('down_payment')->default(0)->after('paid_amount');
            $table->dropColumn('tenor');
        });
    }

    public function down(): void
    {
        Schema::table('pos_sales_installment_plans', function (Blueprint $table) {
            $table->dropColumn('down_payment');
            $table->integer('tenor')->after('paid_amount');
        });
    }
};
