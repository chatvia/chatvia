<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use Spatie\LaravelSettings\Attributes\ShouldBeEncrypted;

/**
 * The OAuth apps of the connectors whose services do not support dynamic
 * client registration (each `PreRegisteredOAuthConnector` reads its own from
 * `connectors.oauth_apps.{slug}.client_*`), and the services behind the
 * built-in Perplexity, Weather and Currency connectors (each workspace adds
 * its own API key).
 */
class ConnectorSettings extends AdminSettings
{
    #[ConfigKey('connectors.oauth_apps.github.client_id')]
    public ?string $github_client_id = null;

    #[ConfigKey('connectors.oauth_apps.github.client_secret'), ShouldBeEncrypted]
    public ?string $github_client_secret = null;

    #[ConfigKey('connectors.oauth_apps.gmail.client_id')]
    public ?string $gmail_client_id = null;

    #[ConfigKey('connectors.oauth_apps.gmail.client_secret'), ShouldBeEncrypted]
    public ?string $gmail_client_secret = null;

    #[ConfigKey('connectors.oauth_apps.google-calendar.client_id')]
    public ?string $google_calendar_client_id = null;

    #[ConfigKey('connectors.oauth_apps.google-calendar.client_secret'), ShouldBeEncrypted]
    public ?string $google_calendar_client_secret = null;

    #[ConfigKey('connectors.oauth_apps.google-drive.client_id')]
    public ?string $google_drive_client_id = null;

    #[ConfigKey('connectors.oauth_apps.google-drive.client_secret'), ShouldBeEncrypted]
    public ?string $google_drive_client_secret = null;

    #[ConfigKey('connectors.oauth_apps.slack.client_id')]
    public ?string $slack_client_id = null;

    #[ConfigKey('connectors.oauth_apps.slack.client_secret'), ShouldBeEncrypted]
    public ?string $slack_client_secret = null;

    #[ConfigKey('connectors.oauth_apps.hubspot.client_id')]
    public ?string $hubspot_client_id = null;

    #[ConfigKey('connectors.oauth_apps.hubspot.client_secret'), ShouldBeEncrypted]
    public ?string $hubspot_client_secret = null;

    #[ConfigKey('connectors.oauth_apps.box.client_id')]
    public ?string $box_client_id = null;

    #[ConfigKey('connectors.oauth_apps.box.client_secret'), ShouldBeEncrypted]
    public ?string $box_client_secret = null;

    #[ConfigKey('connectors.oauth_apps.asana.client_id')]
    public ?string $asana_client_id = null;

    #[ConfigKey('connectors.oauth_apps.asana.client_secret'), ShouldBeEncrypted]
    public ?string $asana_client_secret = null;

    #[ConfigKey('connectors.oauth_apps.front.client_id')]
    public ?string $front_client_id = null;

    #[ConfigKey('connectors.oauth_apps.front.client_secret'), ShouldBeEncrypted]
    public ?string $front_client_secret = null;

    #[ConfigKey('services.perplexity.base_url')]
    public ?string $perplexity_base_url = null;

    #[ConfigKey('services.perplexity.agent_model')]
    public ?string $perplexity_agent_model = null;

    #[ConfigKey('services.perplexity.ask_model')]
    public ?string $perplexity_ask_model = null;

    #[ConfigKey('services.perplexity.research_model')]
    public ?string $perplexity_research_model = null;

    #[ConfigKey('services.openweather.base_url')]
    public ?string $openweather_base_url = null;

    #[ConfigKey('services.exchange_rate.base_url')]
    public ?string $exchange_rate_base_url = null;

    public static function group(): string
    {
        return 'connectors';
    }
}
