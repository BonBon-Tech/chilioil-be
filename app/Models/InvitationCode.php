<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvitationCode extends Model
{
    use HasUuids;
    protected $fillable = [
        'code',
        'plan',
        'months',
        'is_used',
        'used_by',
        'company_id',
        'used_at',
    ];

    protected $casts = [
        'months' => 'integer',
        'is_used' => 'boolean',
        'used_at' => 'datetime',
    ];

    public function usedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
