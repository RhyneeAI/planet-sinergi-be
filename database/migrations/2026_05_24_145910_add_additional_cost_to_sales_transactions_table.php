<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_transactions', function (Blueprint $table) {
            $table->double('additional_cost')->default(0)->after('discount');
            $table->string('additional_cost_note')->nullable()->after('additional_cost');
        });
    }

    public function down(): void
    {
        Schema::table('sales_transactions', function (Blueprint $table) {
            $table->dropColumn(['additional_cost', 'additional_cost_note']);
        });
    }
};