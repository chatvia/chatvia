<?php

namespace App\Identity\Workspace\Actions\Social;

use App\Identity\Workspace\Actions\Account\ClaimUnverifiedAccountAction;
use App\Identity\Workspace\Data\Social\SocialLoginData;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\UserSocial;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sign-in via an external OAuth identity. First tries an exact (provider,
 * provider_id) match on `user_socials`; if none, falls back to a same-email
 * match on `users` and auto-links the social identity so the next sign-in
 * goes through the fast path. Returns null when no account matches — the
 * controller decides how to surface that ("create one" vs "no account").
 *
 * A same-email account that was never verified may have been registered by
 * someone else, who knows its password. It is claimed first
 * ({@see ClaimUnverifiedAccountAction}): verified, given a random password,
 * stripped of any other sign-in and signed out everywhere, so only the owner
 * of the provider identity gets in.
 *
 * Socialite itself stays in the controller: the action operates on the
 * resolved (provider, providerId, email) triple, not on the OAuth client.
 */
class SocialLoginAction
{
    public function __construct(
        private readonly ClaimUnverifiedAccountAction $claimUnverified,
    ) {}

    public function execute(SocialLoginData $data): ?User
    {
        $linked = UserSocial::query()
            ->where('provider', $data->provider)
            ->where('provider_id', $data->providerId)
            ->first();

        if ($linked) {
            return $linked->user;
        }

        if (! $data->email) {
            return null;
        }

        // Pre-hijack / OAuth-merge defence: never link an incoming identity to a
        // pre-existing local account by email the provider says is unverified.
        if ($data->emailVerified === false) {
            return null;
        }

        // Emails are stored lowercase; a provider may send any case.
        $user = User::query()->where('email', Str::lower($data->email))->first();

        if (! $user) {
            return null;
        }

        return DB::transaction(function () use ($user, $data): User {
            $this->claimUnverified->execute($user);

            // Auto-link so the next sign-in hits the fast path above.
            // updateOrCreate is idempotent for the (user, provider) unique pair.
            UserSocial::query()->updateOrCreate(
                ['user_id' => $user->id, 'provider' => $data->provider],
                ['provider_id' => $data->providerId],
            );

            return $user;
        });
    }
}
