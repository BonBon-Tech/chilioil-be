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
        'customer_name',
        'total',
        'sub_total',
        'total_item',
        'type',
        'payment_type',
        'status',
        'online_transaction_revenue',
    ];

    protected $casts = [
        'date' => 'date',
        'total' => 'decimal:2',
        'sub_total' => 'decimal:2',
        'total_item' => 'integer',
        'online_transaction_revenue' => 'decimal:2',
    ];

    public function transactionItems(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function onlineTransactionDetails(): HasMany
    {
        return $this->hasMany(OnlineTransactionDetail::class, 'transaction_id');
    }
}
