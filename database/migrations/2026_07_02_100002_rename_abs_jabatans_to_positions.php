<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('abs_jabatans', 'positions');
    }

    public function down(): void
    {
        Schema::rename('positions', 'abs_jabatans');
    }
};
