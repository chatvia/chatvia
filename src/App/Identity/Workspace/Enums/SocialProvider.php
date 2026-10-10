<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Enums;

/**
 * OAuth/social-login provider that a `user_socials` row links a user to.
 */
enum SocialProvider: string
{
    case Google = 'google';
    case Microsoft = 'microsoft';
    case Slack = 'slack';
    case Github = 'github';
    case LinkedIn = 'linkedin';
    case X = 'x';

    /**
     * Providers with a wired-up OAuth callback, so an account can link them
     * from Settings. LinkedIn / X exist for stored rows but have no Socialite
     * driver yet, so offering them would show a broken Connect button.
     *
     * @return list<self>
     */
    public static function linkable(): array
    {
        return [self::Google, self::Microsoft, self::Slack, self::Github];
    }

    /** The linkable provider for a route value, or null when it isn't one. */
    public static function tryLinkable(string $value): ?self
    {
        $provider = self::tryFrom($value);

        return in_array($provider, self::linkable(), strict: true) ? $provider : null;
    }
}
