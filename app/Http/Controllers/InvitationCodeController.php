<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Repository\InvitationCodeRepository;

class InvitationCodeController extends Controller
{
    protected InvitationCodeRepository $repo;

    public function __construct(InvitationCodeRepository $repo)
    {
        $this->repo = $repo;
    }

    public function index(): \Illuminate\Http\JsonResponse
    {
        $codes = $this->repo->all();
        return ApiResponse::success($codes, 'Invitation codes fetched');
    }

    public function generate(): \Illuminate\Http\JsonResponse
    {
        $code = $this->repo->generate();
        return ApiResponse::success($code, 'Invitation code generated');
    }

    public function destroy(int $id): \Illuminate\Http\JsonResponse
    {
        $deleted = $this->repo->delete($id);

        if (!$deleted) {
            return ApiResponse::error('Kode tidak ditemukan atau sudah digunakan', null, 404);
        }

        return ApiResponse::success(null, 'Invitation code deleted');
    }
}
