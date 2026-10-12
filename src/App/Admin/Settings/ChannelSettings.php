<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use Spatie\LaravelSettings\Attributes\ShouldBeEncrypted;

/**
 * One Meta app per messaging channel (Messenger, Instagram, WhatsApp), read
 * from `services.meta.{channel}` by Domain\Channel\Enums\MetaApp. Telegram,
 * Slack and Teams are set per agent, not here.
 */
class ChannelSettings extends AdminSettings
{
    #[ConfigKey('services.meta.messenger.app_id')]
    public ?string $messenger_app_id = null;

    #[ConfigKey('services.meta.messenger.app_secret'), ShouldBeEncrypted]
    public ?string $messenger_app_secret = null;

    #[ConfigKey('services.meta.messenger.webhook_verify_token'), ShouldBeEncrypted]
    public ?string $messenger_webhook_verify_token = null;

    #[ConfigKey('services.meta.messenger.config_id')]
    public ?string $messenger_config_id = null;

    #[ConfigKey('services.meta.messenger.redirect', defaultPath: '/my/channels/messenger/oauth/callback')]
    public ?string $messenger_redirect = null;

    #[ConfigKey('services.meta.instagram.app_id')]
    public ?string $instagram_app_id = null;

    #[ConfigKey('services.meta.instagram.app_secret'), ShouldBeEncrypted]
    public ?string $instagram_app_secret = null;

    #[ConfigKey('services.meta.instagram.webhook_verify_token'), ShouldBeEncrypted]
    public ?string $instagram_webhook_verify_token = null;

    #[ConfigKey('services.meta.instagram.config_id')]
    public ?string $instagram_config_id = null;

    #[ConfigKey('services.meta.instagram.redirect', defaultPath: '/my/channels/instagram/oauth/callback')]
    public ?string $instagram_redirect = null;

    #[ConfigKey('services.meta.whatsapp.app_id')]
    public ?string $whatsapp_app_id = null;

    #[ConfigKey('services.meta.whatsapp.app_secret'), ShouldBeEncrypted]
    public ?string $whatsapp_app_secret = null;

    #[ConfigKey('services.meta.whatsapp.webhook_verify_token'), ShouldBeEncrypted]
    public ?string $whatsapp_webhook_verify_token = null;

    #[ConfigKey('services.meta.whatsapp.config_id')]
    public ?string $whatsapp_config_id = null;

    #[ConfigKey('services.meta.whatsapp.register_pin'), ShouldBeEncrypted]
    public ?string $whatsapp_register_pin = null;

    public static function group(): string
    {
        return 'channels';
    }
}
