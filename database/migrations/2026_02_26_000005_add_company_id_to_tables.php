<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->uuid('company_id')->nullable()->after('id');
                $blueprint->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
                $blueprint->index('company_id');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropForeign([$table === 'wifi_credentials' ? 'wifi_credentials_company_id_foreign' : "{$table}_company_id_foreign"]);
                $blueprint->dropColumn('company_id');
            });
        }
    }
};
