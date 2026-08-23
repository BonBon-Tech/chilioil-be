<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->date('report_date');
            $table->foreignUuid('restock_creditor_id')->nullable()
                ->constrained('daily_report_creditors')->nullOnDelete();
            $table->decimal('shopeefood_amount', 15, 2)->nullable();
            $table->decimal('grabfood_amount', 15, 2)->nullable();
            $table->decimal('gofood_amount', 15, 2)->nullable();
            $table->decimal('qris_amount', 15, 2)->nullable();
            $table->decimal('cash_amount', 15, 2)->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'store_id', 'report_date']);
            $table->index(['company_id', 'store_id', 'report_date']);
        });

        Schema::create('daily_report_restock_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('daily_report_id')->constrained('daily_reports')->cascadeOnDelete();
            $table->foreignUuid('expense_id')->nullable()->constrained('expenses')->nullOnDelete();
            $table->foreignUuid('expense_category_id')->constrained('expense_categories')->restrictOnDelete();
            $table->string('name');
            $table->decimal('quantity', 15, 3)->nullable();
            $table->string('unit', 30)->nullable();
            $table->decimal('amount', 15, 2);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['daily_report_id', 'deleted_at']);
        });

        Schema::create('daily_report_debt_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('daily_report_id')->constrained('daily_reports')->cascadeOnDelete();
            $table->foreignUuid('creditor_id')->constrained('daily_report_creditors')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->unique(['daily_report_id', 'creditor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_report_debt_payments');
        Schema::dropIfExists('daily_report_restock_items');
        Schema::dropIfExists('daily_reports');
    }
};
