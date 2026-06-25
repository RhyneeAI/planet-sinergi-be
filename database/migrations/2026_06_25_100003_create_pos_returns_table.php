<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_returns', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('sales_transaction_id')->constrained('pos_sales_transactions')->onDelete('cascade');
            $table->foreignId('sales_detail_id')->constrained('pos_sales_details')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('pos_products')->onDelete('restrict');
            $table->integer('qty');
            $table->text('reason');
            $table->double('refund_amount');
            $table->enum('status', ['pending', 'processed', 'done'])->default('processed');
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_returns');
    }
};
