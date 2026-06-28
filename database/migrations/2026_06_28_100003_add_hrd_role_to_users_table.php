<?php

use App\Enums\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
            $table->enum('role', Role::values())->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $roles = array_values(array_filter(Role::values(), fn($r) => $r !== 'HRD'));
            $table->dropColumn('role');
            $table->enum('role', $roles)->after('password');
        });
    }
};
