<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: migrate non-Sale/Purchase data to 'Sale' (safe default)
        DB::statement("UPDATE products SET selling_type = 'Sale' WHERE selling_type NOT IN ('Sale', 'Purchase')");

        // Step 2: alter enum to only allow Sale and Purchase
        DB::statement("ALTER TABLE products MODIFY COLUMN selling_type ENUM('Sale', 'Purchase') NOT NULL DEFAULT 'Sale'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE products MODIFY COLUMN selling_type ENUM('Ingredient', 'Sale', 'Employee') NOT NULL DEFAULT 'Sale'");
    }
};
