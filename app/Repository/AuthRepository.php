<?php

namespace App\Repository;

use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthRepository
{
    public function attemptLogin($credentials)
    {
        if ($token = JWTAuth::attempt($credentials)) {
            $user = User::query()->with(['role', 'company', 'assignedStore'])->where('email', $credentials['email'])->first();
            return [
                'user' => $user,
                'token' => $token
            ];
        }
        return null;
    }

    public function getUser()
    {
        return User::with(['role', 'company', 'assignedStore'])->find(Auth::id());
    }

    public function updateUser($user, $data)
    {
        $user->update($data);
        return $user;
    }

    public function logout()
    {
        $user = Auth::user();
        if ($user) {
            $user->tokens()->delete();
        }
    }
}
