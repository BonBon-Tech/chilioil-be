<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;
use App\Helpers\ApiResponse;

class RoleController extends Controller
{
    public function index(): \Illuminate\Http\JsonResponse
    {
        $roles = Role::all();
        return ApiResponse::success($roles, 'Role list fetched successfully');
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $role = Role::create($request->validate([
            'name' => 'required|unique:roles',
            'description' => 'nullable|string'
        ]));
        return ApiResponse::success($role, 'Role created successfully');
    }

    public function show($id): \Illuminate\Http\JsonResponse
    {
        $role = Role::findOrFail($id);
        return ApiResponse::success($role, 'Role detail fetched successfully');
    }

    public function update(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        $role = Role::findOrFail($id);
        $role->update($request->validate([
            'name' => 'required|unique:roles,name,' . $id,
            'description' => 'nullable|string'
        ]));
        return ApiResponse::success($role, 'Role updated successfully');
    }

    public function destroy($id): \Illuminate\Http\JsonResponse
    {
        Role::destroy($id);
        return ApiResponse::success(null, 'Role deleted successfully');
    }
}
