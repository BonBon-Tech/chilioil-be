<?php

namespace App\Traits;

use App\Helpers\JwtClaims;
use Illuminate\Support\Facades\Auth;

/**
 * Provides getCompanyId() using JWT claims to avoid extra DB queries.
 * Returns null for owner (all companies), or the current user's company_id.
 */
trait UsesCompanyScope
{
    protected function getCompanyId(): ?string
    {
        if (JwtClaims::isOwner()) return null;
        return JwtClaims::companyId();
    }

    protected function getStoreId(): ?string
    {
        if (!$this->hasConsistentTenantIdentity() || !$this->isStaff()) {
            return null;
        }

        return Auth::user()->store_id;
    }

    protected function isStaff(): bool
    {
        return JwtClaims::role() === 'staff' || Auth::user()?->role?->name === 'staff';
    }

    protected function hasConsistentTenantIdentity(): bool
    {
        $user = Auth::user();
        $jwtRole = JwtClaims::role();
        $userRole = $user?->role?->name;

        if (!$user || !$jwtRole || $jwtRole !== $userRole ||
            !JwtClaims::companyId() || JwtClaims::companyId() !== $user->company_id) {
            return false;
        }

        return $userRole !== 'staff' || (
            JwtClaims::storeId() &&
            $user->store_id &&
            JwtClaims::storeId() === $user->store_id
        );
    }
}
