<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_installment_plans', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('sales_transaction_id')->constrained()->onDelete('cascade');
            $table->foreignId('customer_id')->constrained()->onDelete('restrict');
            $table->double('total_amount');
            $table->double('paid_amount')->default(0);
            $table->integer('tenor');
            $table->date('start_date');
            $table->enum('status', ['ACTIVE', 'COMPLETED', 'OVERDUE'])->default('ACTIVE');
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['sales_transaction_id', 'company_id']);
            $table->index(['customer_id', 'company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_installment_plans');
    }
};