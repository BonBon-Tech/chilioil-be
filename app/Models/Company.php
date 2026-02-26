<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use SoftDeletes;

    const PLAN_BASIC = 'basic';
    const PLAN_PRO = 'pro';
    const PLAN_CUSTOM = 'custom';

    protected $fillable = [
        'name',
        'slug',
        'is_demo',
        'plan',
    ];

    protected $casts = [
        'is_demo' => 'boolean',
    ];

    public function isPro(): bool
    {
        return $this->plan === self::PLAN_PRO || $this->plan === self::PLAN_CUSTOM;
    }

    public function isBasic(): bool
    {
        return $this->plan === self::PLAN_BASIC;
    }

    public function hasFeature(string $slug): bool
    {
        return PlanFeature::where('plan', $this->plan)
            ->whereHas('feature', fn($q) => $q->where('slug', $slug))
            ->where('is_active', true)
            ->exists();
    }

    public function getActiveFeatures()
    {
        return Feature::whereHas('planFeatures', function ($q) {
            $q->where('plan', $this->plan)->where('is_active', true);
        })->orderBy('sort_order')->get();
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    public function productCategories(): HasMany
    {
        return $this->hasMany(ProductCategory::class);
    }

    public function expenseCategories(): HasMany
    {
        return $this->hasMany(ExpenseCategory::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function wifiCredentials(): HasMany
    {
        return $this->hasMany(WifiCredential::class);
    }
}
