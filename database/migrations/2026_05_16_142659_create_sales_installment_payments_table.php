<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_installment_payments', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('sales_installment_plan_id')
                ->constrained('sales_installment_plans')
                ->onDelete('cascade');
            $table->integer('installment_number');
            $table->double('paid_amount');
            $table->date('paid_date');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['sales_installment_plan_id', 'company_id']);
            $table->index(['company_id', 'paid_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_installment_payments');
    }
};