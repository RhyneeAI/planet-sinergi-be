<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_mutations', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->enum('type', ['IN', 'OUT', 'OPNAME']);
            $table->integer('quantity');
            $table->integer('stock_before');
            $table->integer('stock_after');
            $table->text('notes')->nullable();
            
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            
            $table->string('reference_type')->nullable(); // 'purchase', 'sale', 'stock_opname'
            $table->unsignedBigInteger('reference_id')->nullable();
            
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();
            
            $table->index(['product_id', 'company_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_mutations');
    }
};