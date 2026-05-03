<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_transactions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('transaction_code')->unique();
            $table->datetime('transaction_date');
            $table->double('discount')->default(0);
            $table->double('total')->default(0);
            $table->double('paid')->default(0);
            $table->enum('payment_type', ['CASH', 'TRANSFER', 'QRIS'])->default('cash');
            $table->enum('transaction_status', ['UNPAID', 'PROCESS', 'PAID', 'CANCEL', 'PENDING'])->default('PENDING');
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->softDeletes();
            $table->timestamps();
            
            $table->index(['company_id', 'transaction_date']);
            $table->index(['user_id', 'company_id']);
            $table->index(['customer_id', 'company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_transactions');
    }
};