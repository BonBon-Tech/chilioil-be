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

    public function index(): \Illuminate\Http\JsonResponse
    {
        $companies = $this->repo->all();
        return ApiResponse::success($companies, 'Companies fetched');
    }

    public function updatePlan(Request $request, int $id): \Illuminate\Http\JsonResponse
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
