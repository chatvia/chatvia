<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use Spatie\LaravelSettings\Attributes\ShouldBeEncrypted;

/**
 * Install-wide options that fit nowhere else: the default language, the
 * security headers, the chat widget, page-view analytics, visitor location,
 * the services behind the built-in tools, and where Horizon sends alerts.
 */
class PlatformSettings extends AdminSettings
{
    #[ConfigKey('app.locale')]
    public ?string $default_locale = null;

    #[ConfigKey('chatvia.security.csp')]
    public ?string $csp_mode = null;

    #[ConfigKey('chatvia.security.csp_report_uri')]
    public ?string $csp_report_uri = null;

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

    public static function group(): string
    {
        return 'platform';
    }
}
