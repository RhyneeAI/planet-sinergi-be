<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_tokens', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();
            $table->string('report_type', 100);
            $table->text('filters');
            $table->string('format', 10);
            $table->string('status', 20)->default('pending');
            $table->string('disk_path')->nullable();
            $table->string('filename')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users');
            $table->foreignId('company_id')->constrained();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_tokens');
    }
};
