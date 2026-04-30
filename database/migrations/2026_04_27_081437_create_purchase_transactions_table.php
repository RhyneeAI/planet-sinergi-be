<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_transactions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('transaction_code')->unique();
            $table->datetime('transaction_date');
            $table->double('discount')->default(0);
            $table->double('total')->default(0);
            $table->foreignId('supplier_id')->constrained()->onDelete('restrict');
            $table->foreignId('user_id')->constrained()->onDelete('restrict');
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->softDeletes();
            $table->timestamps();
            
            $table->index(['company_id', 'transaction_date']);
            $table->index('supplier_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_transactions');
    }
};