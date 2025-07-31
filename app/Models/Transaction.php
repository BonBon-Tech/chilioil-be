<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'date',
        'total',
        'sub_total',
        'total_item',
        'type',
        'payment_type',
        'status'
    ];

    protected $casts = [
        'date' => 'date',
        'total' => 'decimal:2',
        'sub_total' => 'decimal:2',
        'total_item' => 'integer'
    ];

    public function transactionItems(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }
}

