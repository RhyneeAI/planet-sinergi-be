<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ops_edit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('loggable_type', 100);
            $table->unsignedBigInteger('loggable_id');
            $table->text('reason');
            $table->json('old_data');
            $table->json('new_data');
            $table->foreignId('edited_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['loggable_type', 'loggable_id']);
            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_edit_logs');
    }
};
