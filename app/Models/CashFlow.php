<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashFlow extends Model
{
    use SoftDeletes;

    public const TYPE_INCOME = 'INCOME';
    public const TYPE_EXPENSES = 'EXPENSES';

    protected $fillable = [
        'type', // INCOME or EXPENSES
        'store_id',
        'amount',
    ];

    protected $casts = [
        'amount' => 'int',
    ];
}
