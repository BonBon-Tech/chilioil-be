<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TrialRequest extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'email',
        'whatsapp',
        'status',
    ];
}
