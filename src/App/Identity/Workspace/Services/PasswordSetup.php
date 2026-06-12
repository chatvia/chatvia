<?php

namespace App\Identity\Workspace\Services;

use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Tracks accounts that were provisioned with a random password the owner has
 * never seen (trial checkout) and still owe us a real one. The flag lives in
 * the cache — the users table is untouched — and RequirePasswordSetup steers
 * flagged owners to the set-password page until any password save clears it
 * (set-password page, settings, or a forgot-password reset).
 */
class PasswordSetup
{
    public function flag(User $user): void
    {
        Cache::forever($this->key($user), true);
    }

    public function clear(User $user): void
    {
        Cache::forget($this->key($user));
    }

    public function isRequired(User $user): bool
    {
        return Cache::has($this->key($user));
    }

    private function key(User $user): string
    {
        return "password-setup:{$user->id}";
    }
}
