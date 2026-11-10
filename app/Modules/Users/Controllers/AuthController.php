<?php

namespace App\Modules\Users\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Users\Requests\LoginRequest;
use App\Modules\Users\Requests\RegisterRequest;
use App\Modules\Users\Services\UserService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    use ApiResponse;

    protected UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * User login with JWT generation.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->userService->login($request->only('email', 'password'));

        if (!$result) {
            return $this->errorResponse('Invalid email or password.', 401);
        }

        return $this->successResponse($result, 'Logged in successfully.');
    }

    /**
     * User registration with instant JWT.
     * Public registration strictly extracts only customer credentials.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->safe()->only(['name', 'email', 'password']);
        $result = $this->userService->register($data);

        return $this->successResponse($result, 'User registered successfully.', 201);
    }

    /**
     * Get authenticated user profile.
     */
    public function me(): JsonResponse
    {
        $user = $this->userService->profile();

        return $this->successResponse([
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
            'role'  => $user->role,
        ], 'Profile retrieved.');
    }

    /**
     * Logout and invalidate token.
     */
    public function logout(): JsonResponse
    {
        $this->userService->logout();

        return $this->successResponse(null, 'Logged out successfully.');
    }
}
