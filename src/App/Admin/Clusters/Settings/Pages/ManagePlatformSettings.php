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
 * the tracking code, visitor location, the built-in tools, alerts and the
 * number of queue workers.
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
    protected static ?int $navigationSort = 11;

    public static function description(): string
    {
        return 'Language, widget, tracking code, the built-in tools and queue workers.';
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
            SettingsCard::make('widget', 'Chat widget', 'Where the widget may load, and how much it may be used.', Heroicon::OutlinedChatBubbleOvalLeftEllipsis, fn (): array => [
                $this->text('widget_allowed_origins', 'Sites every widget may load on',
                    help: 'Comma-separated hosts, such as "example.com, app.example.com", on top of this install and each agent\'s own sites.'),
            ])->advancedFields(fn (): array => [
                $this->urlOrPath('widget_script_url', 'Widget script address',
                    help: 'The default is the copy this server builds. Enter a full address to load it from a CDN.'),
                $this->integer('widget_stream_per_minute', 'Replies per agent per minute', min: 1),
                $this->integer('widget_stream_per_hour', 'Replies per agent per hour', min: 1),
            ])->summary(fn (): string => $this->valueInUse('widget_stream_per_minute').' replies per agent per minute'),
            SettingsCard::make('tracking', 'Tracking code', 'Code added to every page, such as the script of an analytics service. Nothing is added while it is empty.', Heroicon::OutlinedChartBar, fn (): array => [
                $this->code('tracking_code', 'Code', placeholder: '<script defer src="https://…"></script>',
                    help: 'Added to the <head> of the dashboard, the hosted chat and the sign-in pages, on a live (production) install only.'),
            ])->summary(fn (): string => filled($this->valueInUse('tracking_code')) ? 'On' : 'Off'),
            SettingsCard::make('ip2location', 'IP2Location', 'Finds the country of a visitor from their IP address.', Heroicon::OutlinedMapPin, fn (): array => [
                $this->secret('ip2location_api_key', 'API key'),
            ])->advancedFields(fn (): array => [
                $this->url('ip2location_endpoint', 'Address'),
            ])->required('ip2location_api_key')->console('https://www.ip2location.io/dashboard'),
            SettingsCard::make('tools', 'Built-in tools', 'The services behind the Perplexity, Weather and Currency tools. Each workspace brings its own API key.', Heroicon::OutlinedSquaresPlus, fn (): array => [
                $this->url('perplexity_base_url', 'Perplexity address'),
                $this->text('perplexity_agent_model', 'Perplexity search model'),
                $this->text('perplexity_ask_model', 'Perplexity ask model'),
                $this->text('perplexity_research_model', 'Perplexity research model'),
                $this->url('openweather_base_url', 'OpenWeather address'),
                $this->url('exchange_rate_base_url', 'ExchangeRate-API address'),
            ])->advanced(),
            SettingsCard::make('alerts', 'Alerts', 'Where the queue sends its alerts.', Heroicon::OutlinedBellAlert, fn (): array => [
                $this->email('horizon_notify_email', 'Alert email',
                    help: 'Gets an email when jobs wait too long in the queue. Applies after Horizon restarts.'),
            ])->summary(fn (): string => filled($this->valueInUse('horizon_notify_email')) ? 'To '.$this->valueInUse('horizon_notify_email') : 'No alerts'),
            SettingsCard::make('queue_workers', 'Queue workers', 'How many background jobs run at once. Each worker uses about 80 MB of memory. Applies when the app runs in production (APP_ENV=production, as in Docker), after Horizon restarts (with Docker: ./manage restart horizon).', Heroicon::OutlinedCog8Tooth, fn (): array => [
                $this->integer('chat_workers', 'Chat', help: 'Replies on messaging channels, e-mails and other short jobs.', min: 2, max: 200),
                $this->integer('ingestion_workers', 'Files and websites', help: 'Reading knowledge sources, then exports.', min: 1, max: 200),
                $this->integer('analytics_workers', 'Analytics', help: 'Conversation analysis, evals and audit logs.', min: 1, max: 100),
                $this->integer('media_workers', 'Audio and video', help: 'Transcribing recordings. Each one can take a long time.', min: 1, max: 50),
                $this->integer('voice_workers', 'Live voice calls', help: 'Each voice conversation holds one worker for its whole length, so this is also the most calls at once.', min: 1, max: 200),
            ])->summary(fn (): string => sprintf('Up to %d workers', array_sum(array_map(
                fn (string $property): int => (int) $this->valueInUse($property),
                ['chat_workers', 'ingestion_workers', 'analytics_workers', 'media_workers', 'voice_workers'],
            ))))->advanced(),
        ];
    }

    /** @return array<string, string> */
    private function languages(): array
    {
        return Arr::except(config('chatvia.languages'), 'auto');
    }
}
