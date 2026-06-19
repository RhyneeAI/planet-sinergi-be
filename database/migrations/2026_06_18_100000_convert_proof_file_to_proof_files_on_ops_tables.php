<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['ops_incomes', 'ops_expenses'] as $tableName) {
            if (!Schema::hasColumn($tableName, 'proof_file')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->json('proof_files')->nullable()->after('date');
            });

            DB::table($tableName)
                ->whereNotNull('proof_file')
                ->orderBy('id')
                ->chunkById(100, function ($rows) use ($tableName) {
                    foreach ($rows as $row) {
                        DB::table($tableName)
                            ->where('id', $row->id)
                            ->update(['proof_files' => json_encode([$row->proof_file])]);
                    }
                });

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('proof_file');
            });
        }
    }

    public function down(): void
    {
        foreach (['ops_incomes', 'ops_expenses'] as $tableName) {
            if (!Schema::hasColumn($tableName, 'proof_files')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->string('proof_file')->nullable()->after('date');
            });

            DB::table($tableName)
                ->whereNotNull('proof_files')
                ->orderBy('id')
                ->chunkById(100, function ($rows) use ($tableName) {
                    foreach ($rows as $row) {
                        $files = json_decode($row->proof_files, true) ?: [];
                        DB::table($tableName)
                            ->where('id', $row->id)
                            ->update(['proof_file' => $files[0] ?? null]);
                    }
                });

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('proof_files');
            });
        }
    }
};
