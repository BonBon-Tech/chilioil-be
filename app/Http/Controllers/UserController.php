<?php
// filepath: A:\Project\ChiliOil BE\app\Http\Controllers\UserController.php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Repository\UserRepository;
use App\Helpers\ApiResponse;

class UserController extends Controller
{
    protected UserRepository $users;

    public function __construct(UserRepository $users)
    {
        $this->users = $users;
    }

    public function index(): \Illuminate\Http\JsonResponse
    {
        $users = $this->users->all();
        return ApiResponse::success($users, 'User list fetched successfully');
    }

    public function store(StoreUserRequest $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validated();
        $validated['password'] = bcrypt($validated['password']);
        $user = $this->users->create($validated);
        return ApiResponse::success($user, 'User created successfully');
    }

    public function show($id): \Illuminate\Http\JsonResponse
    {
        $user = $this->users->find($id);
        return ApiResponse::success($user, 'User detail fetched successfully');
    }

    public function update(UpdateUserRequest $request, $id): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validated();
        if (isset($validated['password'])) {
            $validated['password'] = bcrypt($validated['password']);
        }
        $user = $this->users->update($id, $validated);
        return ApiResponse::success($user, 'User updated successfully');
    }

    public function destroy($id): \Illuminate\Http\JsonResponse
    {
        $this->users->delete($id);
        return ApiResponse::success(null, 'User deleted successfully');
    }
}
