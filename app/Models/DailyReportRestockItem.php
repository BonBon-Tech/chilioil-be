<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DailyReportRestockItem extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'daily_report_id', 'creditor_id', 'entry_type', 'expense_id', 'expense_category_id',
        'name', 'quantity', 'unit', 'amount',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'amount' => 'decimal:2',
    ];

    public function creditor(): BelongsTo
    {
        return $this->belongsTo(DailyReportCreditor::class);
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function expenseCategory(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class);
    }
}
