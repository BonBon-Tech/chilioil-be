<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Repository\CompanyRepository;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    protected CompanyRepository $repo;

    public function __construct(CompanyRepository $repo)
    {
        $this->repo = $repo;
    }

    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $search = $request->query('search');
        $perPage = min((int) $request->query('per_page', 10), 50);
        $companies = $this->repo->paginateWithStats($perPage, $search);
        $companies->through(fn($c) => [
            'id'                      => $c->id,
            'name'                    => $c->name,
            'plan'                    => $c->plan,
            'is_demo'                 => $c->is_demo,
            'transaction_count'       => $c->transactions_count,
            'user_count'              => $c->users_count,
            'created_at'              => $c->created_at,
            'subscription_expires_at' => $c->subscription_expires_at?->toDateString(),
            'days_until_expiry'       => $c->daysUntilExpiry(),
            'is_expired'              => $c->isExpired(),
        ]);
        return ApiResponse::success($companies, 'Companies fetched');
    }

    public function updatePlan(Request $request, string $id): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'plan' => 'required|in:basic,pro,custom',
        ]);

        $company = $this->repo->updatePlan($id, $request->plan);

        if (!$company) {
            return ApiResponse::error('Company not found', null, 404);
        }

        return ApiResponse::success($company, 'Plan berhasil diperbarui');
    }

    public function renew(Request $request, string $id): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'months' => 'required|integer|min:1|max:24',
        ]);

        $company = $this->repo->renew($id, (int) $request->months);

        if (!$company) {
            return ApiResponse::error('Company not found', null, 404);
        }

        return ApiResponse::success([
            'id'                      => $company->id,
            'name'                    => $company->name,
            'plan'                    => $company->plan,
            'subscription_expires_at' => $company->subscription_expires_at?->toDateString(),
            'days_until_expiry'       => $company->daysUntilExpiry(),
            'is_expired'              => $company->isExpired(),
        ], 'Subscription berhasil diperpanjang');
    }
}
