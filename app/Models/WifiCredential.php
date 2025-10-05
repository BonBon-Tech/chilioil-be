<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WifiCredential extends Model
{
    protected $fillable = [
        'code',
        'is_active',
    ];
}
