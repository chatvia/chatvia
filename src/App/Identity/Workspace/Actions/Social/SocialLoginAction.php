<?php

namespace App\Identity\Workspace\Actions\Social;

use App\Identity\Workspace\Enums\SocialProvider;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\UserSocial;
use Illuminate\Support\Str;

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
    /**
     * @param  bool|null  $emailVerified  whether the provider asserted the email
     *                                    as verified. `null` = the provider did
     *                                    not say (trusted, current behaviour);
     *                                    `false` = explicitly NOT verified, in
     *                                    which case the same-email auto-link is
     *                                    refused so an attacker controlling an
     *                                    unverified provider email cannot take
     *                                    over a victim's existing account.
     */
    public function execute(SocialProvider $provider, string $providerId, ?string $email, ?bool $emailVerified = null): ?User
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

        // Pre-hijack / OAuth-merge defence: never link an incoming identity to a
        // pre-existing local account by email the provider says is unverified.
        if ($emailVerified === false) {
            return null;
        }

        // Emails are stored lowercase; a provider may send any case.
        $user = User::query()->where('email', Str::lower($email))->first();

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
