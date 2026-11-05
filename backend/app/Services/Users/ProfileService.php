<?php

namespace App\Services\Users;

use App\Enums\AppLocale;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * Self-service changes to the authenticated user's own profile.
 *
 * Only the fields listed here can ever change. Email, status and platform
 * privileges are not editable through these methods.
 */
final class ProfileService
{
    private const EDITABLE_FIELDS = ['name', 'phone', 'timezone'];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(User $user, array $attributes): User
    {
        $user->fill(array_intersect_key($attributes, array_flip(self::EDITABLE_FIELDS)));

        try {
            $user->saveOrFail();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'phone' => [__('validation.unique', ['attribute' => 'phone'])],
            ]);
        }

        return $user;
    }

    public function updateLocale(User $user, AppLocale $locale): User
    {
        $user->locale = $locale->value;
        $user->saveOrFail();

        return $user;
    }
}
