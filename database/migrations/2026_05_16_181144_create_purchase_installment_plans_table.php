<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_purchase_installment_plans', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('purchase_transaction_id')->constrained('pos_purchase_transactions')->onDelete('cascade')->name('fk_pip_purchase_transaction_id');
            $table->foreignId('supplier_id')->constrained('pos_suppliers')->onDelete('restrict')->name('fk_pip_supplier_id');
            $table->double('total_amount');
            $table->double('paid_amount')->default(0);
            $table->date('start_date');
            $table->enum('status', ['ACTIVE', 'COMPLETED', 'OVERDUE'])->default('ACTIVE');
            $table->foreignId('company_id')->constrained()->onDelete('cascade')->name('fk_pip_company_id_plan');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_purchase_installment_plans');
    }
};