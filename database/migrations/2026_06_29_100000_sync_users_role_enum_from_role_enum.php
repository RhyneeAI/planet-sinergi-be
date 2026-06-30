<?php

use App\Enums\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Selaraskan constraint users.role dengan production (main):
     * - Rename data legacy MANAJER_GUDANG / MANAGER_GUDANG → GUDANG
     * - Refresh enum/check constraint ke Role::values() (termasuk KEPALA_GUDANG)
     */
    public function up(): void
    {
        DB::table('users')
            ->whereIn('role', ['MANAJER_GUDANG', 'MANAGER_GUDANG'])
            ->update(['role' => Role::GUDANG->value]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
            $table->enum('role', Role::values())->after('password');
        });
    }

    public function down(): void
    {
        // Irreversible: constraint diselaraskan ke Role::values() saat migrate dijalankan.
    }
};
