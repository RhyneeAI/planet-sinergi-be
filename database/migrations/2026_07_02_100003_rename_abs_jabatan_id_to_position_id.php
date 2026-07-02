<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('abs_employee_profiles', function (Blueprint $table) {
            $table->renameColumn('abs_jabatan_id', 'position_id');
        });
    }

    public function down(): void
    {
        Schema::table('abs_employee_profiles', function (Blueprint $table) {
            $table->renameColumn('position_id', 'abs_jabatan_id');
        });
    }
};
