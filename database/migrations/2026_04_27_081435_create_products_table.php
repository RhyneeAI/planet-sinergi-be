<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('code')->nullable();
            $table->double('base_price')->default(0);
            $table->double('sales_price')->default(0);
            $table->double('last_purchase_price')->default(0);
            $table->integer('stock')->default(0);
            $table->integer('min_stock')->default(0);
            $table->double('discount')->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            
            $table->softDeletes();
            $table->timestamps();
            
            $table->index(['company_id', 'code']);
            $table->index(['category_id', 'company_id']);
            $table->index(['supplier_id', 'company_id']);
            $table->index(['is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};