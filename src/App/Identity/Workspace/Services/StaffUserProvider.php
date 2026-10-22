<?php

namespace App\Identity\Workspace\Services;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Support\Str;

/**
 * The user provider for staff accounts: Laravel's Eloquent provider, with the
 * `email` credential lowered first. Staff emails are stored lowercase (a
 * CHECK holds it), so the framework's own lookups (the password reset
 * broker, the admin panel's sign-in, credential checks) stay a plain
 * `email = ?` that the unique index serves, whatever case the person typed.
 */
class StaffUserProvider extends EloquentUserProvider
{
    /**
     * @param  array<string, mixed>  $credentials
     */
    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials)
    {
        if (filled($credentials['email'] ?? null) && ! is_array($credentials['email'])) {
            $credentials['email'] = Str::lower($credentials['email']);
        }

        return parent::retrieveByCredentials($credentials);
    }
}
