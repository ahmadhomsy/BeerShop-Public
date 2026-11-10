<?php

namespace App\Modules\Users\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class UserService
{
    /**
     * Authenticate user and return JWT token and user info.
     */
    public function login(array $credentials): ?array
    {
        if (!$token = auth('api')->attempt($credentials)) {
            return null;
        }

        $user = auth('api')->user();

        return [
            'token'      => $token,
            'token_type' => 'Bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'user'       => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
            ],
        ];
    }

    /**
     * Register a new user and generate token.
     */
    public function register(array $data): array
    {
        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
            'role'     => $data['role'] ?? 'user',
        ]);

        $token = auth('api')->login($user);

        return [
            'token'      => $token,
            'token_type' => 'Bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'user'       => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
            ],
        ];
    }

    /**
     * Invalidate current token (logout).
     */
    public function logout(): void
    {
        auth('api')->logout();
    }

    /**
     * Get current authenticated user profile.
     */
    public function profile(): ?User
    {
        return auth('api')->user();
    }
}
