<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('pos_marketing_products', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->double('marketing_price')->default(0);
            $table->foreignId('product_id')->constrained('pos_products')->onDelete('cascade');
            $table->foreignId('marketing_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->softDeletes();
            $table->timestamps();
            
            // Prevent duplicate assignment
            $table->unique(['product_id', 'marketing_id']);
            
            $table->index(['product_id']);
            $table->index(['marketing_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_marketing_products');
    }
};