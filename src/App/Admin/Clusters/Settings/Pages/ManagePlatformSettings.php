<?php

declare(strict_types=1);

namespace App\Admin\Clusters\Settings\Pages;

use App\Admin\Clusters\Settings\AdminSettingsPage;
use App\Admin\Clusters\Settings\SettingsCard;
use App\Admin\Settings\PlatformSettings;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;

/**
 * Install-wide options: default language, the chat widget,
 * page-view analytics, visitor location, the built-in tools and alerts.
 */
class ManagePlatformSettings extends AdminSettingsPage
{
    #[\Override]
    protected static string $settings = PlatformSettings::class;

    #[\Override]
    protected static ?string $title = 'Platform';

    #[\Override]
    protected static ?string $slug = 'platform';

    #[\Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    #[\Override]
    protected static ?int $navigationSort = 10;

    public static function description(): string
    {
        return 'Language, widget, analytics and the built-in tools.';
    }

    public static function overviewGroup(): string
    {
        return 'Brand and platform';
    }

    protected function cards(): array
    {
        return [
            SettingsCard::make('language', 'Language', 'The language for people who have not chosen one, and for messaging channels.', Heroicon::OutlinedLanguage, fn (): array => [
                $this->choice('default_locale', 'Default language', $this->languages()),
            ])->summary(fn (): string => $this->languages()[(string) $this->valueInUse('default_locale')] ?? $this->shown($this->valueInUse('default_locale'))),
            SettingsCard::make('widget', 'Chat widget', 'Where the widget may load, and how much it may be used.', Heroicon::OutlinedChatBubbleBottomCenterText, fn (): array => [
                $this->text('widget_allowed_origins', 'Sites every widget may load on',
                    help: 'Comma-separated hosts, such as "example.com, app.example.com", on top of this install and each agent\'s own sites.'),
                $this->urlOrPath('widget_script_url', 'Widget script address',
                    help: 'The default is the copy this server builds. Enter a full address to load it from a CDN.'),
                $this->integer('widget_stream_per_minute', 'Replies per agent per minute', min: 1),
                $this->integer('widget_stream_per_hour', 'Replies per agent per hour', min: 1),
            ])->summary(fn (): string => $this->valueInUse('widget_stream_per_minute').' replies per agent per minute'),
            SettingsCard::make('plausible', 'Plausible', 'Page-view analytics. Nothing is loaded while the domain is empty.', Heroicon::OutlinedChartBar, fn (): array => [
                $this->text('plausible_domain', 'Domain'),
                $this->url('plausible_script_url', 'Script address', help: 'Point it to your own Plausible to self-host.'),
            ])->summary(fn (): string => filled($this->valueInUse('plausible_domain')) ? 'On for '.$this->valueInUse('plausible_domain') : 'Off'),
            SettingsCard::make('ip2location', 'IP2Location', 'Finds the country of a visitor from their IP address.', Heroicon::OutlinedMapPin, fn (): array => [
                $this->secret('ip2location_api_key', 'API key'),
                $this->url('ip2location_endpoint', 'Address'),
            ])->required('ip2location_api_key')->console('https://www.ip2location.io/dashboard'),
            SettingsCard::make('tools', 'Built-in tools', 'The services behind the Perplexity, Weather and Currency tools. Each workspace brings its own API key.', Heroicon::OutlinedWrenchScrewdriver, fn (): array => [
                $this->url('perplexity_base_url', 'Perplexity address'),
                $this->text('perplexity_agent_model', 'Perplexity search model'),
                $this->text('perplexity_ask_model', 'Perplexity ask model'),
                $this->text('perplexity_research_model', 'Perplexity research model'),
                $this->url('openweather_base_url', 'OpenWeather address'),
                $this->url('exchange_rate_base_url', 'ExchangeRate-API address'),
            ]),
            SettingsCard::make('alerts', 'Alerts', 'Where the queue sends its alerts.', Heroicon::OutlinedBellAlert, fn (): array => [
                $this->email('horizon_notify_email', 'Alert email',
                    help: 'Gets an email when jobs wait too long in the queue. Applies after Horizon restarts.'),
            ])->summary(fn (): string => filled($this->valueInUse('horizon_notify_email')) ? 'To '.$this->valueInUse('horizon_notify_email') : 'No alerts'),
        ];
    }

    /** @return array<string, string> */
    private function languages(): array
    {
        return Arr::except(config('chatvia.languages'), 'auto');
    }
}
