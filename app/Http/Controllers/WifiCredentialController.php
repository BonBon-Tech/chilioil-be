<?php

namespace App\Http\Controllers;

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
        return response()->json($data);
    }

    public function show(int $id): JsonResponse
    {
        $wifiCredential = $this->repository->find($id);
        if (!$wifiCredential) {
            return response()->json(['message' => 'Not found'], 404);
        }
        return response()->json($wifiCredential);
    }

    public function store(StoreWifiCredentialRequest $request): JsonResponse
    {
        $wifiCredential = $this->repository->create($request->validated());
        return response()->json($wifiCredential, 201);
    }

    public function update(UpdateWifiCredentialRequest $request, int $id): JsonResponse
    {
        $wifiCredential = $this->repository->find($id);
        if (!$wifiCredential) {
            return response()->json(['message' => 'Not found'], 404);
        }
        $wifiCredential = $this->repository->update($wifiCredential, $request->validated());
        return response()->json($wifiCredential);
    }

    public function destroy(int $id): JsonResponse
    {
        $wifiCredential = $this->repository->find($id);
        if (!$wifiCredential) {
            return response()->json(['message' => 'Not found'], 404);
        }
        $this->repository->delete($wifiCredential);
        return response()->json(['message' => 'Deleted successfully']);
    }
}
