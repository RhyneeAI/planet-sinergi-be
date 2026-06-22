<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_sales_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('marketing_id');
        });
    }

    public function down(): void
    {
        Schema::table('pos_sales_transactions', function (Blueprint $table) {
            $table->foreignId('marketing_id')->nullable()->constrained('users')->onDelete('restrict');
        });
    }
};
