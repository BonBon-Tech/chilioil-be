<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CashChannelMapping extends Model
{
    use HasUuids;

    protected $fillable = ['company_id', 'store_id', 'channel', 'cash_account_id'];
}
