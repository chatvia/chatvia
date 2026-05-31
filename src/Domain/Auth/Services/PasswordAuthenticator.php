<?php

namespace Domain\Auth\Services;

use App\Auth\Models\User;
use Illuminate\Contracts\Auth\StatefulGuard;

/**
 * Thin wrapper around the web auth guard so Actions can validate credentials
 * and log users in without reaching for the Auth facade directly. Keeps the
 * domain layer free of framework facades and gives us a single seam to fake
 * in unit tests.
 */
class PasswordAuthenticator
{
    public function __construct(
        private readonly StatefulGuard $guard,
    ) {}

    /**
     * Check the credentials without altering the auth state. Returns true if
     * the password matches the user identified by $email.
     */
    public function validateCredentials(string $email, string $password): bool
    {
        return $this->guard->validate([
            'email' => $email,
            'password' => $password,
        ]);
    }

    /**
     * Log the user into the session, optionally setting the remember cookie.
     */
    public function login(User $user, bool $remember = false): void
    {
        $this->guard->login($user, $remember);
    }
}
