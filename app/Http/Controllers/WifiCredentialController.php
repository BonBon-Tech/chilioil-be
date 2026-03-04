<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWifiCredentialRequest;
use App\Http\Requests\UpdateWifiCredentialRequest;
use App\Repository\WifiCredentialRepository;
use App\Models\WifiCredential;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WifiCredentialController extends Controller
{
    protected WifiCredentialRepository $repository;

    public function __construct(WifiCredentialRepository $repository)
    {
        $this->repository = $repository;
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);
        $data = $this->repository->paginate($perPage);
        return ApiResponse::success($data, 'WiFi credentials fetched');
    }

    public function show(string $id): JsonResponse
    {
        $wifiCredential = $this->repository->find($id);
        if (!$wifiCredential) {
            return ApiResponse::error('Not found', null, 404);
        }
        return ApiResponse::success($wifiCredential, 'WiFi credential fetched');
    }

    public function store(StoreWifiCredentialRequest $request): JsonResponse
    {
        $wifiCredential = $this->repository->create($request->validated());
        return ApiResponse::success($wifiCredential, 'WiFi credential created', 201);
    }

    public function update(UpdateWifiCredentialRequest $request, string $id): JsonResponse
    {
        $wifiCredential = $this->repository->find($id);
        if (!$wifiCredential) {
            return ApiResponse::error('Not found', null, 404);
        }
        $wifiCredential = $this->repository->update($wifiCredential, $request->validated());
        return ApiResponse::success($wifiCredential, 'WiFi credential updated');
    }

    public function destroy(string $id): JsonResponse
    {
        $wifiCredential = $this->repository->find($id);
        if (!$wifiCredential) {
            return ApiResponse::error('Not found', null, 404);
        }
        $this->repository->delete($wifiCredential);
        return ApiResponse::success(null, 'Deleted successfully');
    }
}
