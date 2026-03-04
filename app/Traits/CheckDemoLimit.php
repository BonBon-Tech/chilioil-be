<?php

namespace App\Traits;

use App\Helpers\ApiResponse;
use Illuminate\Support\Facades\Auth;

trait CheckDemoLimit
{
    protected function checkDemoLimit(string $model, int $limit): ?\Illuminate\Http\JsonResponse
    {
        $user = Auth::user();
        $company = $user->company;

        if ($company && $company->is_demo) {
            $count = $model::where('company_id', $company->id)->count();
            if ($count >= $limit) {
                return ApiResponse::error(
                    'Akun demo telah mencapai batas maksimal. Upgrade ke akun berbayar untuk fitur tanpa batas.',
                    [
                        'is_demo_limit' => true,
                        'limit' => $limit,
                        'current' => $count,
                    ],
                    403
                );
            }
        }

        return null;
    }
}
