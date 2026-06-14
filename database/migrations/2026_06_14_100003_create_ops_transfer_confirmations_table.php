<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ops_transfer_confirmations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('confirmable_type', 100);
            $table->unsignedBigInteger('confirmable_id');
            $table->enum('status', ['PENDING', 'CONFIRMED', 'REJECTED'])->default('PENDING');
            $table->string('mandor_proof_file')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index(['confirmable_type', 'confirmable_id']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_transfer_confirmations');
    }
};
