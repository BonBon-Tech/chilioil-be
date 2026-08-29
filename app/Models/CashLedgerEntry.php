<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CashLedgerEntry extends Model
{
    use HasUuids;

    protected $fillable = [
        'company_id', 'store_id', 'entry_date', 'type', 'source_account_id',
        'destination_account_id', 'amount', 'reference_type', 'reference_id',
        'reference_key', 'note', 'created_by',
    ];

    protected $casts = ['entry_date' => 'date:Y-m-d', 'amount' => 'decimal:2'];
}
