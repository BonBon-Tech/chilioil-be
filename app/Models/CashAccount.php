<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CashAccount extends Model
{
    use HasUuids;

    protected $fillable = ['company_id', 'store_id', 'code', 'name', 'opened_on', 'is_active'];

    protected $casts = ['opened_on' => 'date:Y-m-d', 'is_active' => 'boolean'];
}
