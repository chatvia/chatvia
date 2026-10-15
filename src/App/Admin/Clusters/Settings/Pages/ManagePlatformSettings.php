<?php

declare(strict_types=1);

namespace App\Admin\Clusters\Settings\Pages;

use App\Admin\Clusters\Settings\AdminSettingsPage;
use App\Admin\Clusters\Settings\SettingsCard;
use App\Admin\Settings\PlatformSettings;
use App\Admin\Settings\ServerProgram;
use App\Admin\Settings\ServerProgramFinder;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;

/**
 * Install-wide options: default language, the chat widget,
 * the tracking code, visitor location, the built-in tools, alerts, the
 * number of queue workers, and where the programs that read files are.
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
        return 'Language, widget, tracking code, the built-in tools, queue workers and programs.';
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
                $this->code('tracking_code', 'Code',
                    help: 'Added to the <head> of the dashboard, the hosted chat and the sign-in pages, on a live (production) install only.', placeholder: '<script defer src="https://…"></script>'),
            ])->summary(fn (): string => filled($this->valueInUse('tracking_code')) ? 'On' : 'Off'),
            SettingsCard::make('ip2location', 'IP2Location', 'Finds the country of a visitor from their IP address.', Heroicon::OutlinedMapPin, fn (): array => [
                $this->secret('ip2location_api_key', 'API key'),
            ])->advancedFields(fn (): array => [
                $this->url('ip2location_endpoint', 'Address'),
            ])->required('ip2location_api_key')->console('https://www.ip2location.io/dashboard'),
            SettingsCard::make('perplexity', 'Perplexity', 'The service behind the Perplexity tool (web search). Each workspace adds its own API key.', Heroicon::OutlinedGlobeAlt, fn (): array => [
                $this->url('perplexity_base_url', 'Address'),
                $this->text('perplexity_agent_model', 'Search model',
                    help: 'Used to search the web, fetch a page, search finance data, find people and run code.'),
                $this->text('perplexity_ask_model', 'Ask model',
                    help: 'Used to "ask": a full answer with reasons, from web sources.'),
                $this->text('perplexity_research_model', 'Research model',
                    help: 'Used to "research": a deep look across many sources. It is slower.'),
            ])->advanced(),
            SettingsCard::make('openweather', 'OpenWeather', 'The service behind the Weather tool. Each workspace adds its own API key.', Heroicon::OutlinedCloud, fn (): array => [
                $this->url('openweather_base_url', 'Address'),
            ])->advanced(),
            SettingsCard::make('exchange_rate', 'ExchangeRate-API', 'The service behind the Currency tool. Each workspace adds its own API key.', Heroicon::OutlinedBanknotes, fn (): array => [
                $this->url('exchange_rate_base_url', 'Address'),
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
            SettingsCard::make('programs', 'Programs', 'The programs on this server that read files and make PDFs. Enter a full path for a program that is not found.', Heroicon::OutlinedCommandLine, fn (): array => array_map(
                $this->programField(...),
                ServerProgram::cases(),
            ))->summary(fn (): string => $this->programsSummary())->advanced(),
        ];
    }

    /**
     * The path of a program. Blank runs it by name, from the PATH. Only its
     * name or a full path that ends in it is accepted ({@see ServerProgram}),
     * and a full path must lead to a program on this server. "Detect" fills
     * in the path `which` finds.
     */
    private function programField(ServerProgram $program): TextInput
    {
        return $this->text($program->property(), $program->value)
            ->helperText(fn (): string => $this->programHelp($program))
            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail) use ($program): void {
                $path = is_string($value) ? trim($value) : '';

                if ($path === '' || $path === $program->value) {
                    return;
                }

                if (! $program->acceptsPath($path)) {
                    $fail("Enter \"{$program->value}\", or a full path that ends in /{$program->value}, such as /usr/local/bin/{$program->value}.");
                } elseif (app(ServerProgramFinder::class)->find($path) === null) {
                    $fail("There is no program at {$path} on this server, or it cannot be run.");
                }
            })
            ->hint(fn (): string => $this->programPath($program) !== null ? 'Found' : 'Not found')
            ->hintIcon(fn (): Heroicon => $this->programPath($program) !== null ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedExclamationTriangle)
            ->hintColor(fn (): string => $this->programPath($program) !== null ? 'success' : 'warning')
            ->hintAction($this->detectAction($program));
    }

    /** Runs `which` for the program and puts the path it finds in the field, to save. */
    private function detectAction(ServerProgram $program): Action
    {
        return Action::make('detect_'.$program->value)
            ->label('Detect')
            ->icon(Heroicon::OutlinedMagnifyingGlass)
            ->tooltip("Runs \"which {$program->value}\" on the server and puts the path it finds here")
            ->action(function (Set $set) use ($program): void {
                $path = app(ServerProgramFinder::class)->detect($program);

                if ($path === null) {
                    Notification::make()->warning()->title("{$program->value} was not found")->body('Install it on the server, or enter its full path.')->send();

                    return;
                }

                $set($program->property(), $path);

                Notification::make()->success()->title("Found {$path}")->body('Save changes to use it.')->send();
            });
    }

    /** What the program is for, then where it was found. */
    private function programHelp(ServerProgram $program): string
    {
        $found = $this->programPath($program);

        return $program->purpose().' '.($found !== null ? "Runs {$found}." : 'Click Detect, or enter its full path.');
    }

    /** The file this server runs for the program's path in use, or null when there is none. */
    private function programPath(ServerProgram $program): ?string
    {
        return app(ServerProgramFinder::class)->find((string) $this->valueInUse($program->property()));
    }

    private function programsSummary(): string
    {
        $missing = array_filter(ServerProgram::cases(), fn (ServerProgram $program): bool => $this->programPath($program) === null);

        return $missing === []
            ? 'All found'
            : 'Not found: '.implode(', ', array_map(fn (ServerProgram $program): string => $program->value, $missing));
    }

    /** @return array<string, string> */
    private function languages(): array
    {
        return Arr::except(config('chatvia.languages'), 'auto');
    }
}
