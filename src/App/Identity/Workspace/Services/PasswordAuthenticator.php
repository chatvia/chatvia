<?php

namespace App\Identity\Workspace\Services;

use App\Identity\Workspace\Models\User;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Support\Facades\Hash;

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
     * Return the user whose email and password match, or null.
     *
     * Every call does exactly one password hash, also when no account has this
     * email (or the account has no password, e.g. social sign-in only). So the
     * response time is the same for a known and an unknown email, and timing
     * can't be used to find out which emails have an account.
     */
    public function findUserByCredentials(string $email, string $password): ?User
    {
        $user = User::query()->where('email', $email)->first();
        $hash = $user?->password;

        if (! is_string($hash) || $hash === '') {
            // Same cost as Hash::check() below, but the result is thrown away.
            Hash::make($password);

            return null;
        }

        return Hash::check($password, $hash) ? $user : null;
    }

    /**
     * Log the user into the session, optionally setting the remember cookie.
     */
    public function login(User $user, bool $remember = false): void
    {
        $this->guard->login($user, $remember);
    }
}
