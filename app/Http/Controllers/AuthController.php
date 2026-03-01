<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Company;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ValidateInvitationCodeRequest;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Repository\AuthRepository;
use App\Repository\InvitationCodeRepository;
use App\Helpers\ApiResponse;

class AuthController extends Controller
{
    protected AuthRepository $authRepo;
    protected InvitationCodeRepository $invitationCodeRepo;

    public function __construct(AuthRepository $authRepo, InvitationCodeRepository $invitationCodeRepo)
    {
        $this->authRepo = $authRepo;
        $this->invitationCodeRepo = $invitationCodeRepo;
    }

    public function validateInvitationCode(ValidateInvitationCodeRequest $request): \Illuminate\Http\JsonResponse
    {
        $code = $this->invitationCodeRepo->findUnusedByCode($request->code);

        if (!$code) {
            return ApiResponse::error('Kode undangan tidak valid atau sudah digunakan', null, 422);
        }

        return ApiResponse::success([
            'valid' => true,
            'code' => $code->code,
            'plan' => $code->plan,
        ], 'Kode undangan valid');
    }

    public function register(RegisterRequest $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validated();

        $invitationCode = $this->invitationCodeRepo->findUnusedByCode($validated['code']);
        if (!$invitationCode) {
            return ApiResponse::error('Kode undangan tidak valid atau sudah digunakan', null, 422);
        }

        try {
            $result = DB::transaction(function () use ($validated, $invitationCode) {
                // 1. Create company — use plan from invitation code
                $company = Company::create([
                    'name' => $validated['company_name'],
                    'slug' => Str::slug($validated['company_name']) . '-' . Str::random(4),
                    'is_demo' => false,
                    'plan' => $invitationCode->plan ?? Company::PLAN_BASIC,
                ]);

                // 2. Create default store
                Store::create([
                    'name' => $validated['company_name'],
                    'company_id' => $company->id,
                ]);

                // 3. Get admin role
                $adminRole = DB::table('roles')->where('name', 'admin')->first();

                // 4. Create user
                $user = User::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                    'role_id' => $adminRole->id,
                    'company_id' => $company->id,
                ]);

                // 5. Mark invitation code as used (track which company registered with it)
                $this->invitationCodeRepo->markAsUsed($invitationCode, $user->id, $company->id);

                // 6. Generate JWT
                $token = JWTAuth::fromUser($user);

                $user->load(['role', 'company']);

                return [
                    'token' => $token,
                    'user' => $user,
                ];
            });

            return ApiResponse::success($result, 'Registrasi berhasil');
        } catch (\Exception $e) {
            return ApiResponse::error('Registrasi gagal: ' . $e->getMessage(), null, 500);
        }
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
        $data = $request->only(['name', 'email']);
        $updatedUser = $this->authRepo->updateUser($user, $data);
        return ApiResponse::success($updatedUser, 'User updated successfully');
    }

    public function forgotPassword(ForgotPasswordRequest $request): \Illuminate\Http\JsonResponse
    {
        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        // Return token directly for mobile app (no email sending for now)
        return ApiResponse::success([
            'token' => $token,
        ], 'Token reset password berhasil dibuat. Gunakan token ini untuk mereset password.');
    }

    public function resetPassword(ResetPasswordRequest $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validated();

        $record = DB::table('password_reset_tokens')
            ->where('email', $validated['email'])
            ->first();

        if (!$record) {
            return ApiResponse::error('Token tidak valid', null, 422);
        }

        if (!Hash::check($validated['token'], $record->token)) {
            return ApiResponse::error('Token tidak valid', null, 422);
        }

        // Check if token expired (24 hours)
        if (now()->diffInMinutes($record->created_at) > 1440) {
            return ApiResponse::error('Token sudah kadaluarsa', null, 422);
        }

        $user = User::where('email', $validated['email'])->first();
        if (!$user) {
            return ApiResponse::error('User tidak ditemukan', null, 404);
        }

        $user->update(['password' => Hash::make($validated['password'])]);

        DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();

        return ApiResponse::success(null, 'Password berhasil direset');
    }

    public function demoCredentials(): \Illuminate\Http\JsonResponse
    {
        return ApiResponse::success([
            'email' => 'demo@example.com',
            'password' => 'demo123',
        ], 'Demo credentials');
    }
}
