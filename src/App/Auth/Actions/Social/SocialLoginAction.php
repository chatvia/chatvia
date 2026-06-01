<?php

namespace App\Auth\Actions\Social;

use App\Auth\Models\User;
use App\Auth\Models\UserSocial;
use Support\Enums\User\SocialProvider;

/**
 * Sign-in via an external OAuth identity. First tries an exact (provider,
 * provider_id) match on `user_socials`; if none, falls back to a same-email
 * match on `users` and auto-links the social identity so the next sign-in
 * goes through the fast path. Returns null when no account matches — the
 * controller decides how to surface that ("create one" vs "no account").
 *
 * Socialite itself stays in the controller: the action operates on the
 * resolved (provider, providerId, email) triple, not on the OAuth client.
 */
class SocialLoginAction
{
    public function execute(SocialProvider $provider, string $providerId, ?string $email): ?User
    {
        $linked = UserSocial::query()
            ->where('provider', $provider)
            ->where('provider_id', $providerId)
            ->first();

        if ($linked) {
            return $linked->user;
        }

        if (! $email) {
            return null;
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            return null;
        }

        // Auto-link so the next sign-in hits the fast path above. updateOrCreate
        // is idempotent for the (user, provider) unique pair.
        UserSocial::query()->updateOrCreate(
            ['user_id' => $user->id, 'provider' => $provider],
            ['provider_id' => $providerId],
        );

        return $user;
    }
}
