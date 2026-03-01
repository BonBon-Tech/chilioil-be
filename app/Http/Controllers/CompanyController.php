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
            'id'                => $c->id,
            'name'              => $c->name,
            'plan'              => $c->plan,
            'is_demo'           => $c->is_demo,
            'transaction_count' => $c->transactions_count,
            'user_count'        => $c->users_count,
            'created_at'        => $c->created_at,
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
}
