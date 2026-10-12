<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use Spatie\LaravelSettings\Attributes\ShouldBeEncrypted;

/**
 * The OAuth apps of the connectors whose services do not support dynamic
 * client registration, read from `connectors.{slug}.oauth_client_*` in
 * config/connectors.php.
 */
class ConnectorSettings extends AdminSettings
{
    #[ConfigKey('connectors.github.oauth_client_id')]
    public ?string $github_client_id = null;

    #[ConfigKey('connectors.github.oauth_client_secret'), ShouldBeEncrypted]
    public ?string $github_client_secret = null;

    #[ConfigKey('connectors.gmail.oauth_client_id')]
    public ?string $gmail_client_id = null;

    #[ConfigKey('connectors.gmail.oauth_client_secret'), ShouldBeEncrypted]
    public ?string $gmail_client_secret = null;

    #[ConfigKey('connectors.google-calendar.oauth_client_id')]
    public ?string $google_calendar_client_id = null;

    #[ConfigKey('connectors.google-calendar.oauth_client_secret'), ShouldBeEncrypted]
    public ?string $google_calendar_client_secret = null;

    #[ConfigKey('connectors.google-drive.oauth_client_id')]
    public ?string $google_drive_client_id = null;

    #[ConfigKey('connectors.google-drive.oauth_client_secret'), ShouldBeEncrypted]
    public ?string $google_drive_client_secret = null;

    #[ConfigKey('connectors.slack.oauth_client_id')]
    public ?string $slack_client_id = null;

    #[ConfigKey('connectors.slack.oauth_client_secret'), ShouldBeEncrypted]
    public ?string $slack_client_secret = null;

    #[ConfigKey('connectors.hubspot.oauth_client_id')]
    public ?string $hubspot_client_id = null;

    #[ConfigKey('connectors.hubspot.oauth_client_secret'), ShouldBeEncrypted]
    public ?string $hubspot_client_secret = null;

    #[ConfigKey('connectors.box.oauth_client_id')]
    public ?string $box_client_id = null;

    #[ConfigKey('connectors.box.oauth_client_secret'), ShouldBeEncrypted]
    public ?string $box_client_secret = null;

    #[ConfigKey('connectors.asana.oauth_client_id')]
    public ?string $asana_client_id = null;

    #[ConfigKey('connectors.asana.oauth_client_secret'), ShouldBeEncrypted]
    public ?string $asana_client_secret = null;

    public static function group(): string
    {
        return 'connectors';
    }
}
