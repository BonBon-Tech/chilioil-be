<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use App\Helpers\JwtClaims;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TenantOnly
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (!$user?->company_id || JwtClaims::isOwner() || $user->role?->name === 'owner') {
            return ApiResponse::error('Forbidden', null, 403);
        }

        return $next($request);
    }
}
