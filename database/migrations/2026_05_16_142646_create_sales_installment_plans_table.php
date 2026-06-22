<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_sales_installment_plans', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('sales_transaction_id')->constrained('pos_sales_transactions')->onDelete('cascade')->name('fk_sip_sales_transaction_id');
            $table->foreignId('customer_id')->constrained('pos_customers')->onDelete('restrict')->name('fk_sip_customer_id');
            $table->double('total_amount');
            $table->double('paid_amount')->default(0);
            // $table->date('paid_date')->nullable()->change();
            $table->integer('tenor');
            $table->date('start_date');
            $table->enum('status', ['ACTIVE', 'COMPLETED', 'OVERDUE'])->default('ACTIVE');
            $table->foreignId('company_id')->constrained()->onDelete('cascade')->name('fk_sip_company_id_plan');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_sales_installment_plans');
    }
};