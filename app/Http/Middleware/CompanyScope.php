<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use App\Helpers\JwtClaims;
use Closure;
use Illuminate\Http\Request;

class CompanyScope
{
    public function handle(Request $request, Closure $next)
    {
        // Owner can see all companies — skip scoping
        if (JwtClaims::isOwner()) {
            return $next($request);
        }

        $companyId = JwtClaims::companyId();
        if (!$companyId) {
            return ApiResponse::error('User tidak terdaftar di perusahaan manapun', null, 403);
        }

        // Set company_id on request for repositories to use
        $request->merge(['company_id' => $companyId]);

        return $next($request);
    }
}
