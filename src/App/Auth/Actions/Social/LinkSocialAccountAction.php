<?php

namespace App\Auth\Actions\Social;

use App\Auth\Exceptions\SocialAccountAlreadyLinkedException;
use App\Auth\Models\User;
use App\Auth\Models\UserSocial;
use Support\Enums\User\SocialProvider;

/**
 * Idempotently links an external OAuth identity to a user. Re-running the
 * same (user, provider, provider_id) is a no-op that just refreshes the
 * cached `provider_email`. If the same provider identity is already linked
 * to a *different* user, throws so the controller can surface a clean error.
 */
class LinkSocialAccountAction
{
    /**
     * @throws SocialAccountAlreadyLinkedException
     */
    public function execute(User $user, SocialProvider $provider, string $providerId): UserSocial
    {
        $existing = UserSocial::query()
            ->where('provider', $provider)
            ->where('provider_id', $providerId)
            ->first();

        if ($existing !== null && $existing->user_id !== $user->id) {
            throw new SocialAccountAlreadyLinkedException(
                "This {$provider->value} account is already linked to another user.",
            );
        }

        return UserSocial::query()->updateOrCreate(
            ['user_id' => $user->id, 'provider' => $provider],
            ['provider_id' => $providerId],
        );
    }
}
