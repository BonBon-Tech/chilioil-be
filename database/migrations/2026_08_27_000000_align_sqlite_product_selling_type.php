<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->enum('selling_type', ['Ingredient', 'Sale', 'Employee', 'Purchase'])
                ->default('Sale')
                ->change();
        });
        DB::table('products')->where('selling_type', 'Ingredient')->update(['selling_type' => 'Purchase']);
        DB::table('products')->where('selling_type', 'Employee')->update(['selling_type' => 'Sale']);

        Schema::table('products', function (Blueprint $table) {
            $table->enum('selling_type', ['Sale', 'Purchase'])
                ->default('Sale')
                ->change();
        });

        Schema::table('stock_opnames', function (Blueprint $table) {
            $table->enum('status', [
                'draft', 'in_progress', 'waiting_approval', 'completed',
                'pending', 'approved', 'rejected', 'cancelled',
            ])->default('pending')->change();
        });
        DB::table('stock_opnames')->whereIn('status', ['draft', 'in_progress', 'waiting_approval'])->update(['status' => 'pending']);
        DB::table('stock_opnames')->where('status', 'completed')->update(['status' => 'approved']);

        Schema::table('stock_opnames', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])
                ->default('pending')
                ->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->enum('selling_type', ['Ingredient', 'Sale', 'Employee', 'Purchase'])
                ->default('Sale')
                ->change();
        });
        DB::table('products')->where('selling_type', 'Purchase')->update(['selling_type' => 'Ingredient']);

        Schema::table('products', function (Blueprint $table) {
            $table->enum('selling_type', ['Ingredient', 'Sale', 'Employee'])
                ->change();
        });

        Schema::table('stock_opnames', function (Blueprint $table) {
            $table->enum('status', [
                'draft', 'in_progress', 'waiting_approval', 'completed',
                'pending', 'approved', 'rejected', 'cancelled',
            ])->default('pending')->change();
        });
        DB::table('stock_opnames')->where('status', 'pending')->update(['status' => 'in_progress']);
        DB::table('stock_opnames')->where('status', 'approved')->update(['status' => 'completed']);
        DB::table('stock_opnames')->whereIn('status', ['rejected', 'cancelled'])->update(['status' => 'draft']);

        Schema::table('stock_opnames', function (Blueprint $table) {
            $table->enum('status', ['draft', 'in_progress', 'waiting_approval', 'completed'])
                ->default('draft')
                ->change();
        });
    }
};
