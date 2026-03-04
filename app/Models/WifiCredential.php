<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class WifiCredential extends Model
{
    use HasUuids;
    protected $fillable = [
        'code',
        'is_active',
        'company_id',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
