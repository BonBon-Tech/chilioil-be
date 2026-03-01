<?php

namespace App\Traits;

use App\Helpers\JwtClaims;

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
}
