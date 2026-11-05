<?php

namespace App\Http\Controllers\Api\V1\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\UpdateLocaleRequest;
use App\Http\Requests\Users\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Services\Users\ProfileService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * The authenticated user's own profile. There is no user id in these routes,
 * so a user can only ever change their own record.
 */
class ProfileController extends Controller
{
    public function __construct(private readonly ProfileService $profiles) {}

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->profiles->update($request->user(), $request->validated());

        return ApiResponse::success(new UserResource($user), __('api.users.profile_updated'));
    }

    public function updateLocale(UpdateLocaleRequest $request): JsonResponse
    {
        $user = $this->profiles->updateLocale($request->user(), $request->locale());

        return ApiResponse::success(new UserResource($user), __('api.users.locale_updated'));
    }
}
