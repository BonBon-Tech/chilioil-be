<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Expand enum to allow both old and new values temporarily
        DB::statement("ALTER TABLE stock_opnames MODIFY COLUMN status ENUM(
            'draft','in_progress','waiting_approval','completed',
            'pending','approved','rejected','cancelled'
        ) NOT NULL DEFAULT 'pending'");

        // Step 2: Migrate old values to new
        DB::statement("UPDATE stock_opnames SET status = 'pending'  WHERE status IN ('draft', 'in_progress', 'waiting_approval')");
        DB::statement("UPDATE stock_opnames SET status = 'approved' WHERE status = 'completed'");

        // Step 3: Lock to new values only
        DB::statement("ALTER TABLE stock_opnames MODIFY COLUMN status ENUM(
            'pending','approved','rejected','cancelled'
        ) NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE stock_opnames MODIFY COLUMN status ENUM(
            'pending','approved','rejected','cancelled',
            'draft','in_progress','waiting_approval','completed'
        ) NOT NULL DEFAULT 'pending'");

        DB::statement("UPDATE stock_opnames SET status = 'waiting_approval' WHERE status = 'pending'");
        DB::statement("UPDATE stock_opnames SET status = 'completed'        WHERE status = 'approved'");
        DB::statement("UPDATE stock_opnames SET status = 'in_progress'      WHERE status = 'rejected'");
        DB::statement("UPDATE stock_opnames SET status = 'draft'            WHERE status = 'cancelled'");

        DB::statement("ALTER TABLE stock_opnames MODIFY COLUMN status ENUM(
            'draft','in_progress','waiting_approval','completed'
        ) NOT NULL DEFAULT 'draft'");
    }
};
