<?php

namespace App\Models;

use App\Repository\CashLedgerRepository;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class Store extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'logo',
        'slug',
        'company_id',
    ];

    protected $appends = ['logo_url'];

    protected static function booted(): void
    {
        static::created(function (Store $store) {
            if ($store->company_id && Schema::hasTable('cash_accounts') && Schema::hasTable('cash_channel_mappings')) {
                app(CashLedgerRepository::class)->ensureDefaults($store->company_id, $store->id);
            }
        });
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo ? url(Storage::url($this->logo)) : null;
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
