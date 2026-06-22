<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_purchase_transactions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('transaction_code')->unique();
            $table->datetime('transaction_date');
            $table->double('discount')->default(0);
            $table->double('total')->default(0);
            $table->double('paid')->default(0);
            $table->enum('payment_type', ['CASH', 'TRANSFER', 'QRIS', 'CICIL'])->default('CASH');
            $table->enum('transaction_status', ['UNPAID', 'PROCESS', 'PAID', 'CANCEL', 'PENDING'])->default('PENDING');
            $table->foreignId('supplier_id')->constrained('pos_suppliers')->onDelete('restrict');
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->softDeletes();
            $table->timestamps();
            
            $table->index(['company_id', 'transaction_date']);
            $table->index('supplier_id');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_purchase_transactions');
    }
};