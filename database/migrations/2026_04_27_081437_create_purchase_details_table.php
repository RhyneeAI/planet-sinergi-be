<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_purchase_details', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('purchase_id')->constrained('pos_purchase_transactions')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('pos_products')->onDelete('restrict');
            $table->integer('quantity')->default(0);
            $table->double('buy_price')->default(0);
            $table->double('subtotal')->default(0);
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->softDeletes();
            $table->timestamps();
            
            $table->index(['purchase_id']);
            $table->index(['product_id']);
            $table->index(['company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_purchase_details');
    }
};