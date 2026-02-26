<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    private array $tables = [
        'users',
        'stores',
        'product_categories',
        'expense_categories',
        'expenses',
        'transactions',
        'wifi_credentials',
    ];

    public function up(): void
    {
        // Create the existing company
        DB::table('companies')->insert([
            'id' => 1,
            'name' => 'Jajaneun Chili Oil',
            'slug' => 'jajaneun-chili-oil',
            'is_demo' => false,
            'plan' => 'pro',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Assign all existing data to company_id = 1
        foreach ($this->tables as $table) {
            DB::table($table)->whereNull('company_id')->update(['company_id' => 1]);
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            DB::table($table)->where('company_id', 1)->update(['company_id' => null]);
        }

        DB::table('companies')->where('id', 1)->delete();
    }
};
