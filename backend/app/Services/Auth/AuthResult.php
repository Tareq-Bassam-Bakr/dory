<?php

namespace App\Services\Auth;

use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Outcome of a successful registration or login.
 *
 * The plain-text token exists only here, in memory, to be returned once to
 * the client. Only its SHA-256 hash is stored.
 */
final readonly class AuthResult
{
    public function __construct(
        public User $user,
        public string $plainTextToken,
        public ?CarbonInterface $expiresAt,
    ) {}
}
