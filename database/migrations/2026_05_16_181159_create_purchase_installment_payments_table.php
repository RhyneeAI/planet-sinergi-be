<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_purchase_installment_payments', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('purchase_installment_plan_id')->constrained('pos_purchase_installment_plans')->onDelete('cascade')->name('fk_pip_purchase_installment_plan_id'); // ← custom nama
            $table->integer('installment_number');
            $table->double('paid_amount');
            $table->date('paid_date');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict')->name('fk_pip_created_by'); 
            $table->foreignId('company_id')->constrained()->onDelete('cascade')->name('fk_pip_company_id');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['purchase_installment_plan_id', 'company_id'], 'pip_plan_company_idx');
            $table->index(['company_id', 'paid_date'], 'pip_company_paid_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_purchase_installment_payments');
    }
};