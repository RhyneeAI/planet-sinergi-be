<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('ops_transfer_confirmations', 'mandor_proof_file')) {
            return;
        }

        Schema::table('ops_transfer_confirmations', function (Blueprint $table) {
            $table->json('mandor_proof_files')->nullable()->after('status');
        });

        DB::table('ops_transfer_confirmations')
            ->whereNotNull('mandor_proof_file')
            ->orderBy('id')
            ->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('ops_transfer_confirmations')
                        ->where('id', $row->id)
                        ->update(['mandor_proof_files' => json_encode([$row->mandor_proof_file])]);
                }
            });

        Schema::table('ops_transfer_confirmations', function (Blueprint $table) {
            $table->dropColumn('mandor_proof_file');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('ops_transfer_confirmations', 'mandor_proof_files')) {
            return;
        }

        Schema::table('ops_transfer_confirmations', function (Blueprint $table) {
            $table->string('mandor_proof_file')->nullable()->after('status');
        });

        DB::table('ops_transfer_confirmations')
            ->whereNotNull('mandor_proof_files')
            ->orderBy('id')
            ->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    $files = json_decode($row->mandor_proof_files, true) ?: [];
                    DB::table('ops_transfer_confirmations')
                        ->where('id', $row->id)
                        ->update(['mandor_proof_file' => $files[0] ?? null]);
                }
            });

        Schema::table('ops_transfer_confirmations', function (Blueprint $table) {
            $table->dropColumn('mandor_proof_files');
        });
    }
};
