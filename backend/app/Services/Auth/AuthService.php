<?php

namespace App\Services\Auth;

use App\Exceptions\AccountInactiveException;
use App\Exceptions\InvalidCredentialsException;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Registration, login and logout for token-based (mobile) clients.
 */
final class AuthService
{
    /**
     * Hash compared against when the email is unknown, so a failed login
     * takes the same time whether or not the account exists.
     */
    private static ?string $timingSafetyHash = null;

    /**
     * @param  array{name: string, email: string, password: string, phone?: ?string, locale?: string, timezone?: string}  $attributes
     */
    public function register(array $attributes, string $deviceName): AuthResult
    {
        try {
            $result = DB::transaction(function () use ($attributes, $deviceName): AuthResult {
                $user = new User;
                $user->fill($attributes);
                $user->saveOrFail();

                return $this->issueToken($user, $deviceName);
            });
        } catch (UniqueConstraintViolationException) {
            // Lost a race with a concurrent registration for the same email or
            // phone after validation passed. Report it like the unique rule does.
            throw ValidationException::withMessages([
                'email' => [__('validation.unique', ['attribute' => 'email'])],
            ]);
        }

        event(new Registered($result->user));

        return $result;
    }

    /**
     * @throws InvalidCredentialsException
     * @throws AccountInactiveException
     */
    public function login(string $email, string $password, string $deviceName): AuthResult
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            Hash::check($password, self::$timingSafetyHash ??= Hash::make(Str::random(40)));

            throw new InvalidCredentialsException;
        }

        if (! Hash::check($password, $user->password)) {
            throw new InvalidCredentialsException;
        }

        if (! $user->isActive()) {
            throw new AccountInactiveException;
        }

        if (Hash::needsRehash($user->password)) {
            $user->password = $password; // Re-hashed by the "hashed" cast.
        }

        $user->forceFill(['last_login_at' => now()])->saveOrFail();

        return $this->issueToken($user, $deviceName);
    }

    /**
     * Revokes only the token used for this request (this device).
     */
    public function logout(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }
    }

    private function issueToken(User $user, string $deviceName): AuthResult
    {
        $minutes = config('sanctum.expiration');
        $expiresAt = $minutes ? now()->addMinutes((int) $minutes) : null;

        $token = $user->createToken($deviceName, ['*'], $expiresAt);

        return new AuthResult($user, $token->plainTextToken, $expiresAt);
    }
}
