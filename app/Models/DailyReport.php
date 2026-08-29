<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DailyReport extends Model
{
    use HasUuids;

    protected $fillable = [
        'company_id', 'store_id', 'report_date', 'restock_creditor_id',
        'shopeefood_amount', 'grabfood_amount', 'gofood_amount',
        'qris_amount', 'cash_amount', 'cash_mapping_snapshot',
    ];

    protected $casts = [
        'report_date' => 'date:Y-m-d',
        'shopeefood_amount' => 'decimal:2',
        'grabfood_amount' => 'decimal:2',
        'gofood_amount' => 'decimal:2',
        'qris_amount' => 'decimal:2',
        'cash_amount' => 'decimal:2',
        'cash_mapping_snapshot' => 'array',
    ];

    public function restockItems()
    {
        return $this->hasMany(DailyReportRestockItem::class);
    }

    public function restockCreditor()
    {
        return $this->belongsTo(DailyReportCreditor::class, 'restock_creditor_id');
    }

    public function debtPayments()
    {
        return $this->hasMany(DailyReportDebtPayment::class);
    }
}
