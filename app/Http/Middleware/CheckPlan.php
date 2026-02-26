<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckPlan
{
    public function handle(Request $request, Closure $next, string $featureSlug)
    {
        $user = Auth::user();

        if (!$user) {
            return ApiResponse::error('Unauthorized', null, 401);
        }

        // Owner bypasses plan check
        if ($user->role && $user->role->name === 'owner') {
            return $next($request);
        }

        $company = $user->company;

        if (!$company) {
            return ApiResponse::error('User tidak terdaftar di perusahaan manapun', null, 403);
        }

        if (!$company->hasFeature($featureSlug)) {
            return ApiResponse::error(
                'Fitur ini tidak tersedia di paket Anda. Upgrade untuk mengakses fitur ini.',
                [
                    'feature' => $featureSlug,
                    'current_plan' => $company->plan,
                ],
                403
            );
        }

        return $next($request);
    }
}
