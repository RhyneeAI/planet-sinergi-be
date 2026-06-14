<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM(
            'SUPERADMIN',
            'OWNER',
            'ADMIN',
            'MARKETING',
            'KASIR',
            'MANDOR',
            'KARYAWAN'
        ) NOT NULL");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM(
            'SUPERADMIN',
            'OWNER',
            'MARKETING'
        ) NOT NULL");
    }
};
