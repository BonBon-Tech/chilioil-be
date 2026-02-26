<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CompanyScope
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (!$user) {
            return ApiResponse::error('Unauthorized', null, 401);
        }

        // Owner can see all companies — skip scoping
        if ($user->role && $user->role->name === 'owner') {
            return $next($request);
        }

        if (!$user->company_id) {
            return ApiResponse::error('User tidak terdaftar di perusahaan manapun', null, 403);
        }

        // Set company_id on request for repositories to use
        $request->merge(['company_id' => $user->company_id]);

        return $next($request);
    }
}
