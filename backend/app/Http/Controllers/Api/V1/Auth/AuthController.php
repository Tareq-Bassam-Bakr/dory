<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\AuthTokenResource;
use App\Http\Resources\UserResource;
use App\Services\Auth\AuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->auth->register($request->userAttributes(), $request->deviceName());
        $result->user->load('memberships.business');

        return ApiResponse::created(new AuthTokenResource($result), __('api.auth.registered'));
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->auth->login(
            $request->validated('email'),
            $request->validated('password'),
            $request->validated('device_name'),
        );

        // Same user shape as /auth/me, so clients know the memberships
        // (and the right home workspace) right after signing in.
        $result->user->load('memberships.business');

        return ApiResponse::success(new AuthTokenResource($result), __('api.auth.logged_in'));
    }

    public function logout(Request $request): JsonResponse
    {
        $this->auth->logout($request->user());

        return ApiResponse::success(message: __('api.auth.logged_out'));
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('memberships.business');

        return ApiResponse::success(new UserResource($user));
    }
}
