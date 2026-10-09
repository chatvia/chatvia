<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Enums;

/**
 * The two token requests the connector OAuth flow makes, with the wording a
 * failure of each one is reported in.
 */
enum OAuthGrant: string
{
    case AuthorizationCode = 'authorization_code';
    case RefreshToken = 'refresh_token';

    public function blockedMessage(): string
    {
        return match ($this) {
            self::AuthorizationCode => 'Token exchange blocked: unsafe token endpoint.',
            self::RefreshToken => 'Token refresh blocked: unsafe token endpoint.',
        };
    }

    public function failedMessage(string $reason): string
    {
        return match ($this) {
            self::AuthorizationCode => 'Token exchange failed: '.$reason,
            self::RefreshToken => 'Token refresh failed: '.$reason,
        };
    }

    public function rejectedMessage(int $status, string $body): string
    {
        return match ($this) {
            self::AuthorizationCode => 'The authorization server rejected the token exchange ('.$status.'): '.$body,
            self::RefreshToken => 'Token refresh was rejected ('.$status.'): '.$body,
        };
    }

    public function missingTokenMessage(): string
    {
        return match ($this) {
            self::AuthorizationCode => 'The authorization server returned no access_token.',
            self::RefreshToken => 'Token refresh response had no access_token.',
        };
    }
}
