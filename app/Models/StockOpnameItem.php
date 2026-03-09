<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class StockOpnameItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'stock_opname_id',
        'product_id',
        'product_name',
        'uom',
        'last_known_stock',
        'counted_stock',
        'variance',
        'counted_by',
        'counted_at',
        'notes',
    ];

    protected $casts = [
        'last_known_stock' => 'float',
        'counted_stock'    => 'float',
        'variance'         => 'float',
        'counted_at'       => 'datetime',
    ];

    public function stockOpname()
    {
        return $this->belongsTo(StockOpname::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function countedBy()
    {
        return $this->belongsTo(User::class, 'counted_by');
    }
}
