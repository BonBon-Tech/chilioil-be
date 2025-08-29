<?php

namespace Database\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Fix: Use 'CASH' instead of 'CSH' to match existing data and validation
        DB::statement("ALTER TABLE transactions MODIFY COLUMN payment_type ENUM('QRIS', 'CASH', 'GOPAY', 'SHOPEEPAY', 'OVO', 'BANK_TRANSFER') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE transactions MODIFY COLUMN payment_type ENUM('QRIS', 'CASH', 'GOPAY', 'SHOPEEPAY', 'OVO') NOT NULL");
    }
};
