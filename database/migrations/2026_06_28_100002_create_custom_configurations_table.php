<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->text('value');
            $table->text('description')->nullable();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['key', 'company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_configurations');
    }
};
