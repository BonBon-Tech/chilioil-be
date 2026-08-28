<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use Closure;
use Illuminate\Http\Request;

class CompanyScope
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $role = $user?->role?->name;
        $companyId = $user?->company_id;

        // Owner can see all companies — skip scoping
        if ($role === 'owner') {
            return $next($request);
        }

        if (!$companyId) {
            return ApiResponse::error('User tidak terdaftar di perusahaan manapun', null, 403);
        }

        // Set company_id on request for repositories to use
        $request->merge(['company_id' => $companyId]);

        return $next($request);
    }
}
