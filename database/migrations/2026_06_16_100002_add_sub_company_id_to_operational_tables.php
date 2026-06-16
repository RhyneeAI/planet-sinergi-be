<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ops_incomes', function (Blueprint $table) {
            $table->foreignId('sub_company_id')
                ->nullable()
                ->after('mandor_id')
                ->constrained('ops_sub_companies')
                ->nullOnDelete();
        });

        Schema::table('ops_expenses', function (Blueprint $table) {
            $table->foreignId('sub_company_id')
                ->nullable()
                ->after('mandor_id')
                ->constrained('ops_sub_companies')
                ->nullOnDelete();
        });

        Schema::table('ops_wallets', function (Blueprint $table) {
            $table->dropForeign(['mandor_id']);
            $table->dropUnique(['mandor_id']);
            $table->foreign('mandor_id')->references('id')->on('users')->restrictOnDelete();

            $table->foreignId('sub_company_id')
                ->nullable()
                ->after('mandor_id')
                ->constrained('ops_sub_companies')
                ->cascadeOnDelete();

            $table->unique('sub_company_id');
        });
    }

    public function down(): void
    {
        Schema::table('ops_wallets', function (Blueprint $table) {
            $table->dropUnique(['sub_company_id']);
            $table->dropConstrainedForeignId('sub_company_id');

            $table->dropForeign(['mandor_id']);
            $table->unique('mandor_id');
            $table->foreign('mandor_id')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::table('ops_expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sub_company_id');
        });

        Schema::table('ops_incomes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sub_company_id');
        });
    }
};
