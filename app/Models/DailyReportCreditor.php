<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DailyReportCreditor extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'company_id',
        'store_id',
        'name',
        'opening_balance',
        'opening_date',
        'is_active',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'opening_date' => 'date:Y-m-d',
        'is_active' => 'boolean',
    ];
}
