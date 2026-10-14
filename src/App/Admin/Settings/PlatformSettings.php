<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use Spatie\LaravelSettings\Attributes\ShouldBeEncrypted;

/**
 * Install-wide options that fit nowhere else: the default language, the
 * chat widget, page-view analytics, visitor location,
 * the services behind the built-in tools, where Horizon sends alerts, how
 * many queue workers it may run, and when the admin setup of the install was
 * completed.
 */
class PlatformSettings extends AdminSettings
{
    /** @see SetupStatus */
    #[ConfigKey('chatvia.admin.setup_status')]
    public ?string $setup_status = null;

    #[ConfigKey('app.locale')]
    public ?string $default_locale = null;

    #[ConfigKey('chatvia.widget.extra_allowed_origins')]
    public ?string $widget_allowed_origins = null;

    #[ConfigKey('chatvia.widget.script_url_embed')]
    public ?string $widget_script_url = null;

    #[ConfigKey('chatvia.widget.stream_per_agent_per_minute')]
    public ?int $widget_stream_per_minute = null;

    #[ConfigKey('chatvia.widget.stream_per_agent_per_hour')]
    public ?int $widget_stream_per_hour = null;

    #[ConfigKey('services.plausible.domain')]
    public ?string $plausible_domain = null;

    #[ConfigKey('services.plausible.script_url')]
    public ?string $plausible_script_url = null;

    #[ConfigKey('services.ip2location.key'), ShouldBeEncrypted]
    public ?string $ip2location_api_key = null;

    #[ConfigKey('services.ip2location.endpoint')]
    public ?string $ip2location_endpoint = null;

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

    #[ConfigKey('horizon.notify_email')]
    public ?string $horizon_notify_email = null;

    // The most workers per Horizon supervisor (config/horizon.php). Horizon
    // reads them when it starts.
    #[ConfigKey('horizon.environments.production.chat.maxProcesses')]
    public ?int $chat_workers = null;

    #[ConfigKey('horizon.environments.production.ingestion.maxProcesses')]
    public ?int $ingestion_workers = null;

    #[ConfigKey('horizon.environments.production.analytics.maxProcesses')]
    public ?int $analytics_workers = null;

    #[ConfigKey('horizon.environments.production.media.maxProcesses')]
    public ?int $media_workers = null;

    public static function group(): string
    {
        return 'platform';
    }
}
