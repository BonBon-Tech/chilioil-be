<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Fix any existing null last_known_stock values — should always be 0 if unknown
        DB::statement("UPDATE stock_opname_items SET last_known_stock = 0 WHERE last_known_stock IS NULL");

        // Ensure the column always has a DB-level default of 0
        DB::statement("ALTER TABLE stock_opname_items MODIFY COLUMN last_known_stock DECIMAL(18,2) NOT NULL DEFAULT 0");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE stock_opname_items MODIFY COLUMN last_known_stock DECIMAL(18,2) NOT NULL DEFAULT 0");
    }
};
