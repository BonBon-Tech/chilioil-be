<?php

namespace App\Http\Middleware;

use Closure;
use App\Helpers\JwtClaims;
use Tymon\JWTAuth\Facades\JWTAuth;
use Exception;
use Illuminate\Http\Request;

class JwtMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        JwtClaims::flush(); // Clear per-request cache

        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (Exception $e) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if (!$user ||
            !JwtClaims::role() ||
            JwtClaims::role() !== $user->role?->name ||
            JwtClaims::companyId() !== $user->company_id ||
            JwtClaims::storeId() !== $user->store_id) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
