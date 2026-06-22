<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_stock_mutations', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->enum('type', [
                'PURCHASE_IN',
                'SALES_OUT',
                'ADJUST_IN',
                'ADJUST_OUT',
                'OPNAME',
            ]);
            $table->integer('quantity');
            $table->integer('stock_before');
            $table->integer('stock_after');
            $table->text('notes')->nullable();

            $table->foreignId('product_id')->constrained('pos_products')->onDelete('cascade');
            $table->foreignId('company_id')->constrained()->onDelete('cascade');

            // reference_id saja, type sudah implisit menjelaskan asalnya
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['product_id', 'company_id', 'created_at']);
            $table->index(['reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_stock_mutations');
    }
};