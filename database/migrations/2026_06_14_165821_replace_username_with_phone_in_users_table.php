<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Hapus kolom username
            $table->dropUnique(['username']);
            $table->dropColumn('username');
            
            // Pastikan kolom phone ada dan unique
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone')->nullable()->after('email');
            }
            $table->unique('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->string('username')->unique();
        });
    }
};