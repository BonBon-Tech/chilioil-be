<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'transaction_id',
        'product_id',
        'image_path',
        'store_id',
        'name',
        'code',
        'price',
        'qty',
        'total_price'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'qty' => 'integer',
        'total_price' => 'decimal:2'
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function getImagePathAttribute($value): ?string
    {
        return $value ? url('storage/' . $value) : null;
    }
}

