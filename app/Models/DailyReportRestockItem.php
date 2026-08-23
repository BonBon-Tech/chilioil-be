<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DailyReportRestockItem extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'daily_report_id', 'expense_id', 'expense_category_id',
        'name', 'quantity', 'unit', 'amount',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'amount' => 'decimal:2',
    ];
}
