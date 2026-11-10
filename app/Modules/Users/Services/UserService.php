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
     * Register a new customer user and generate JWT token.
     * Enforces default customer role server-side. Client-supplied roles are strictly ignored.
     */
    public function register(array $data): array
    {
        // Enforce the default customer role on the server regardless of input
        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
            'role'     => User::ROLE_USER,
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
     * Provision a privileged user account (Internal / Trusted Admin operation).
     * Administrative account provisioning must remain a separate trusted operation.
     */
    public function createPrivilegedUser(array $data, string $role): User
    {
        $validRoles = [User::ROLE_ADMIN, User::ROLE_SELLER, User::ROLE_USER];
        if (!in_array($role, $validRoles, true)) {
            throw new \InvalidArgumentException("Invalid role specified: {$role}");
        }

        return User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
            'role'     => $role,
        ]);
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
