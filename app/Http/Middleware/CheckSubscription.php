<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckSubscription
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (!$user) {
            return ApiResponse::error('Unauthorized', null, 401);
        }

        // Owner bypasses subscription check
        if ($user->role && $user->role->name === 'owner') {
            return $next($request);
        }

        $company = $user->company;

        if ($company && $company->isExpired()) {
            return ApiResponse::error(
                'Langganan perusahaan Anda telah berakhir. Hubungi owner untuk memperpanjang.',
                ['code' => 'subscription_expired'],
                403
            );
        }

        return $next($request);
    }
}
