<?php

declare(strict_types=1);

namespace Domain\Channel\Enums;

use Domain\Channel\Services\MetaOAuthClient;

/**
 * The channels a workspace connects through Facebook Login for Business, each
 * on its own Meta app ({@see MetaApp}). The value doubles as the dashboard's
 * channel key (`?open=`, field errors).
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

    /** The Meta app this channel connects through. */
    public function app(): MetaApp
    {
        return match ($this) {
            self::Messenger => MetaApp::Messenger,
            self::Instagram => MetaApp::Instagram,
        };
    }
}
