<?php

namespace Domain\Channel\Enums;

use Domain\Channel\Services\MetaOAuthClient;

/**
 * The channels a workspace connects through Facebook Login for Business on
 * Chatvia's single Meta app. Each may use its own Login-for-Business
 * configuration and callback URL; unset, both fall back to the shared ones.
 * The value doubles as the dashboard's channel key (`?open=`, field errors).
 */
enum MetaOAuthChannel: string
{
    case Messenger = 'messenger';
    case Instagram = 'instagram';

    /** The channel's product name, for log lines. */
    public function label(): string
    {
        return match ($this) {
            self::Messenger => 'Messenger',
            self::Instagram => 'Instagram',
        };
    }

    /** The classic OAuth scopes, used when no Login-for-Business configuration is set. */
    public function scopes(): string
    {
        return match ($this) {
            self::Messenger => MetaOAuthClient::MESSENGER_SCOPES,
            self::Instagram => MetaOAuthClient::INSTAGRAM_SCOPES,
        };
    }

    /** This channel's own Login-for-Business configuration, or null for the shared one. */
    public function configId(): ?string
    {
        return match ($this) {
            self::Messenger => null,
            self::Instagram => self::filled(config('services.meta.instagram_config_id') ?: config('services.meta.config_id')),
        };
    }

    /**
     * This channel's own OAuth callback URL, or null for the shared one. The
     * code exchange must send the same URL the authorize step used.
     */
    public function redirectUri(): ?string
    {
        return match ($this) {
            self::Messenger => null,
            self::Instagram => self::filled(config('services.meta.instagram_redirect') ?: config('services.meta.redirect')),
        };
    }

    private static function filled(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
