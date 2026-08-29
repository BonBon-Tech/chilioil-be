<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('code', 60);
            $table->string('name', 100);
            $table->date('opened_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'store_id', 'code']);
            $table->index(['company_id', 'store_id', 'is_active']);
        });

        Schema::create('cash_channel_mappings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('channel', 30);
            $table->foreignUuid('cash_account_id')->constrained('cash_accounts')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'store_id', 'channel']);
        });

        Schema::create('cash_ledger_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->date('entry_date');
            $table->string('type', 30);
            $table->foreignUuid('source_account_id')->nullable()->constrained('cash_accounts')->restrictOnDelete();
            $table->foreignUuid('destination_account_id')->nullable()->constrained('cash_accounts')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('reference_type', 40)->nullable();
            $table->uuid('reference_id')->nullable();
            $table->string('reference_key', 60)->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'store_id', 'entry_date']);
            $table->index(['source_account_id', 'entry_date']);
            $table->index(['destination_account_id', 'entry_date']);
            $table->unique(['reference_type', 'reference_id', 'reference_key'], 'cash_ledger_reference_unique');
        });

        Schema::table('daily_report_restock_items', function (Blueprint $table) {
            $table->foreignUuid('creditor_id')->nullable()->after('daily_report_id')
                ->constrained('daily_report_creditors')->restrictOnDelete();
            $table->string('entry_type', 30)->default('legacy_restock')->after('creditor_id');
        });

        Schema::table('daily_report_debt_payments', function (Blueprint $table) {
            $table->foreignUuid('cash_account_id')->nullable()->after('creditor_id')
                ->constrained('cash_accounts')->restrictOnDelete();
        });
        Schema::table('daily_reports', function (Blueprint $table) {
            $table->json('cash_mapping_snapshot')->nullable()->after('cash_amount');
        });

        DB::table('daily_report_restock_items')->whereNull('creditor_id')->update([
            'creditor_id' => DB::raw('(SELECT restock_creditor_id FROM daily_reports WHERE daily_reports.id = daily_report_restock_items.daily_report_id)'),
        ]);

        DB::table('stores')->select(['id', 'company_id'])->orderBy('id')->chunk(100, function ($stores) {
            foreach ($stores as $store) {
                $this->seedDefaults($store->company_id, $store->id);
            }
        });
    }

    public function down(): void
    {
        Schema::table('daily_reports', function (Blueprint $table) {
            $table->dropColumn('cash_mapping_snapshot');
        });
        Schema::table('daily_report_debt_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cash_account_id');
        });
        Schema::table('daily_report_restock_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('creditor_id');
            $table->dropColumn('entry_type');
        });
        Schema::dropIfExists('cash_ledger_entries');
        Schema::dropIfExists('cash_channel_mappings');
        Schema::dropIfExists('cash_accounts');
    }

    private function seedDefaults(string $companyId, string $storeId): void
    {
        $ids = [];
        foreach (['bca' => 'BCA', 'mandiri' => 'Mandiri', 'cash' => 'Cash'] as $code => $name) {
            $id = (string) Str::uuid();
            DB::table('cash_accounts')->insertOrIgnore([
                'id' => $id,
                'company_id' => $companyId,
                'store_id' => $storeId,
                'code' => $code,
                'name' => $name,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $ids[$code] = DB::table('cash_accounts')
                ->where('company_id', $companyId)->where('store_id', $storeId)->where('code', $code)
                ->value('id');
        }

        foreach (['shopeefood' => 'mandiri', 'grabfood' => 'bca', 'gofood' => 'mandiri', 'qris' => 'mandiri', 'cash' => 'cash'] as $channel => $code) {
            DB::table('cash_channel_mappings')->insertOrIgnore([
                'id' => (string) Str::uuid(),
                'company_id' => $companyId,
                'store_id' => $storeId,
                'channel' => $channel,
                'cash_account_id' => $ids[$code],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
