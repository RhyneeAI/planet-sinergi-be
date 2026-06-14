<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ops_expenses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->decimal('amount', 15, 2);
            $table->date('date');
            $table->string('proof_file')->nullable();
            $table->text('note')->nullable();
            $table->enum('expense_type', ['INTERNAL', 'MANDOR']);
            $table->foreignId('mandor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['company_id', 'date']);
            $table->index('mandor_id');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_expenses');
    }
};
