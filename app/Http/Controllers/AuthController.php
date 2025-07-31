<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Repository\AuthRepository;
use App\Helpers\ApiResponse;

class AuthController extends Controller
{
    protected AuthRepository $authRepo;

    public function __construct(AuthRepository $authRepo)
    {
        $this->authRepo = $authRepo;
    }

    public function register(RegisterRequest $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        try {
            $token = JWTAuth::fromUser($user);
        } catch (JWTException $e) {
            return ApiResponse::error('Could not create token', null, 500);
        }

        return ApiResponse::success([
            'token' => $token,
            'user' => $user,
        ], 'Register successful');
    }

    public function login(LoginRequest $request): \Illuminate\Http\JsonResponse
    {
        $credentials = $request->validated();
        $result = $this->authRepo->attemptLogin($credentials);

        if (!$result) {
            return ApiResponse::error('Invalid credentials', null, 401);
        }

        return ApiResponse::success([
            'user' => $result['user'],
            'token' => $result['token']
        ], 'Login successful');
    }

    public function logout(): \Illuminate\Http\JsonResponse
    {
        $this->authRepo->logout();
        return ApiResponse::success(null, 'Logout successful');
    }

    public function getUser(): \Illuminate\Http\JsonResponse
    {
        $user = $this->authRepo->getUser();
        return ApiResponse::success($user, 'User fetched successfully');
    }

    public function updateUser(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $this->authRepo->getUser();
        $data = $request->only(['name', 'email']); // Adjust fields as needed
        $updatedUser = $this->authRepo->updateUser($user, $data);
        return ApiResponse::success($updatedUser, 'User updated successfully');
    }
}
