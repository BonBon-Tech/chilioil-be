<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add assigned store to users (staff assigned to specific store, null = all stores)
        Schema::table('users', function (Blueprint $table) {
            $table->foreignUuid('store_id')->nullable()->after('company_id')
                ->constrained('stores')->nullOnDelete();
        });

        // Add store_id to expenses for store-level filtering
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignUuid('store_id')->nullable()->after('company_id')
                ->constrained('stores')->nullOnDelete();
        });

        // Add store_id to transactions for POS store tracking
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignUuid('store_id')->nullable()->after('company_id')
                ->constrained('stores')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['store_id']);
            $table->dropColumn('store_id');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['store_id']);
            $table->dropColumn('store_id');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['store_id']);
            $table->dropColumn('store_id');
        });
    }
};
