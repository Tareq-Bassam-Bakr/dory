<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

/**
 * Password reset through Laravel's password broker.
 *
 * Reset tokens are stored hashed in password_reset_tokens and expire after
 * auth.passwords.users.expire minutes.
 */
final class PasswordResetService
{
    /**
     * Sends a reset link if the account exists.
     *
     * The outcome is intentionally not returned: the API answers the same way
     * for known and unknown emails, so accounts cannot be enumerated.
     */
    public function sendResetLink(string $email): void
    {
        Password::sendResetLink(['email' => $email]);
    }

    /**
     * Sets the new password and revokes every API token of the user, signing
     * out all devices.
     *
     * @param  array{email: string, token: string, password: string, password_confirmation: string}  $credentials
     *
     * @throws ValidationException When the token or email is invalid.
     */
    public function reset(array $credentials): void
    {
        $status = Password::reset($credentials, function (User $user, string $password): void {
            DB::transaction(function () use ($user, $password): void {
                $user->forceFill(['password' => $password])->saveOrFail();
                $user->tokens()->delete();
            });

            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            // Unknown email and invalid token get the same message on purpose.
            throw ValidationException::withMessages([
                'token' => [__('api.auth.reset_token_invalid')],
            ]);
        }
    }
}
