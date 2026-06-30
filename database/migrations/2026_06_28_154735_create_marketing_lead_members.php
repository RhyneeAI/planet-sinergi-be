<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_lead_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketing_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('leader_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['marketing_id', 'leader_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_lead_members');
    }
};