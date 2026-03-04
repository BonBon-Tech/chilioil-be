<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\Company;

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
        // Create the existing company using Eloquent (generates UUID automatically)
        $company = Company::create([
            'name' => 'Jajaneun Chili Oil',
            'slug' => 'jajaneun-chili-oil',
            'is_demo' => false,
            'plan' => 'pro',
        ]);

        // Assign all existing data to this company
        foreach ($this->tables as $table) {
            DB::table($table)->whereNull('company_id')->update(['company_id' => $company->id]);
        }
    }

    public function down(): void
    {
        $company = DB::table('companies')->where('slug', 'jajaneun-chili-oil')->first();
        if ($company) {
            foreach ($this->tables as $table) {
                DB::table($table)->where('company_id', $company->id)->update(['company_id' => null]);
            }
            DB::table('companies')->where('id', $company->id)->delete();
        }
    }
};
