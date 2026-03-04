<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OwnerOnly
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (!$user || !$user->role || $user->role->name !== 'owner') {
            return ApiResponse::error('Forbidden', null, 403);
        }

        return $next($request);
    }
}
