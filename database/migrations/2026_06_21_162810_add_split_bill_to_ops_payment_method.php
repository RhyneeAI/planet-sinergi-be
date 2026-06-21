<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TABLES = ['ops_incomes', 'ops_expenses'];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            DB::statement(
                "ALTER TABLE \"{$tableName}\" DROP CONSTRAINT IF EXISTS \"{$tableName}_payment_method_check\""
            );

            DB::statement(
                "ALTER TABLE \"{$tableName}\" ADD CONSTRAINT \"{$tableName}_payment_method_check\" "
                . "CHECK (payment_method::text = ANY (ARRAY['TRANSFER'::character varying, 'CASH'::character varying, 'SPLIT_BILL'::character varying]::text[]))"
            );
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            DB::statement(
                "ALTER TABLE \"{$tableName}\" DROP CONSTRAINT IF EXISTS \"{$tableName}_payment_method_check\""
            );

            DB::statement(
                "ALTER TABLE \"{$tableName}\" ADD CONSTRAINT \"{$tableName}_payment_method_check\" "
                . "CHECK (payment_method::text = ANY (ARRAY['TRANSFER'::character varying, 'CASH'::character varying]::text[]))"
            );
        }
    }
};
