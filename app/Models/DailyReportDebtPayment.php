<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DailyReportDebtPayment extends Model
{
    use HasUuids;

    protected $fillable = ['daily_report_id', 'creditor_id', 'cash_account_id', 'amount', 'note'];

    protected $casts = ['amount' => 'decimal:2'];
}
