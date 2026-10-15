<?php

declare(strict_types=1);

namespace App\Admin\Clusters\Settings\Pages;

use App\Admin\Clusters\Settings\AdminSettingsPage;
use App\Admin\Clusters\Settings\SettingsCard;
use App\Admin\Settings\HorizonWorkers;
use App\Admin\Settings\PlatformSettings;
use App\Admin\Settings\ServerProgram;
use App\Admin\Settings\ServerProgramFinder;
use App\Admin\Settings\ToolRecording;
use App\Admin\StaffTool;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;

/**
 * Install-wide options: default language, the chat widget, the tracking
 * code, visitor location, alerts, whether Pulse and Spectra record, the
 * number of queue workers, where the programs that read files are, and the
 * panel's Logs page.
 */
class ManagePlatformSettings extends AdminSettingsPage
{
    /** The Horizon supervisors of config/horizon.php, and the setting that holds the most workers of each. */
    private const array WORKER_SETTINGS = [
        'chat' => 'chat_workers',
        'ingestion' => 'ingestion_workers',
        'analytics' => 'analytics_workers',
        'media' => 'media_workers',
        'voice' => 'voice_workers',
    ];

    /** What the Queue workers card says about the queue ({@see horizonState()}). */
    private const string ONLY_PRODUCTION = 'Production only';

    private const string NOT_RUNNING = 'Queue not running';

    private const string RESTARTING = 'Queue restarting';

    private const string RESTART_NEEDED = 'Restart needed';

    /** The .env switch that leaves a tool out of the install, by its recording setting. */
    private const array TOOL_SWITCHES = [
        'pulse_enabled' => 'PULSE_ENABLED',
        'spectra_enabled' => 'SPECTRA_ENABLED',
    ];

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
        return 'Language, widget, tracking code, visitor location, Pulse, Spectra, queue workers, programs and logs.';
    }

    /**
     * The Development card shows or hides Logs in the sidebar, so the
     * sidebar is drawn again with the saved choice.
     *
     * `pulse:check` (Pulse's server stats) runs for hours and never sees a
     * save, so a change of the Pulse switch restarts it: the process that
     * keeps it running starts it again with the saved choice.
     */
    #[\Override]
    public function save(): void
    {
        $pulse = $this->valueInUse('pulse_enabled');

        parent::save();

        $this->dispatch('refresh-sidebar');

        if ($this->valueInUse('pulse_enabled') !== $pulse) {
            Artisan::call('pulse:restart');
        }
    }

    public static function overviewGroup(): string
    {
        return 'Platform and brand';
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
            SettingsCard::make('alerts', 'Alerts', 'Where the queue sends its alerts.', Heroicon::OutlinedBellAlert, fn (): array => [
                $this->email('horizon_notify_email', 'Alert email',
                    help: 'Gets an email when jobs wait too long in the queue. Applies after Horizon restarts.'),
            ])->summary(fn (): string => filled($this->valueInUse('horizon_notify_email')) ? 'To '.$this->valueInUse('horizon_notify_email') : 'No alerts'),
            SettingsCard::make('pulse', 'Pulse', 'The app\'s health: slow pages, queries and jobs, errors, queues and the servers.', Heroicon::OutlinedHeart, fn (): array => [
                $this->recordingToggle('pulse_enabled', 'Record app health', help: 'Data older than 7 days is removed.'),
            ])->summary(fn (): string => $this->recordingSummary('pulse_enabled'))
                ->console($this->toolUrl(StaffTool::Pulse), 'Open Pulse'),
            SettingsCard::make('spectra', 'Spectra', 'A log of every AI call: the model, the tokens, the cost and the time it took.', Heroicon::OutlinedCpuChip, fn (): array => [
                $this->recordingToggle('spectra_enabled', 'Record AI calls'),
                $this->toggle('spectra_store_content', 'Save prompts and replies',
                    help: 'Also keeps the text sent to the AI and its answer, which includes what visitors write. Off keeps only the counts, costs and times.'),
                $this->integer('spectra_retention_days', 'Keep AI calls for (days)', help: 'Older calls are removed every night.', min: 1, max: 3650),
            ])->summary(fn (): string => $this->recordingSummary('spectra_enabled'))
                ->console($this->toolUrl(StaffTool::Spectra), 'Open Spectra'),
            SettingsCard::make('queue_workers', 'Queue workers', 'How many background jobs run at once. Each worker uses about 80 MB of memory. Applies when the app runs in production (APP_ENV=production, as in Docker): save, then restart the queue.', Heroicon::OutlinedCog8Tooth, fn (): array => [
                $this->integer('chat_workers', 'Chat', help: 'Replies on messaging channels, e-mails and other short jobs.', min: 2, max: 200),
                $this->integer('ingestion_workers', 'Files and websites', help: 'Reading knowledge sources, then exports.', min: 1, max: 200),
                $this->integer('analytics_workers', 'Analytics', help: 'Conversation analysis, evals and audit logs.', min: 1, max: 100),
                $this->integer('media_workers', 'Audio and video', help: 'Transcribing recordings. Each one can take a long time.', min: 1, max: 50),
                $this->integer('voice_workers', 'Live voice calls', help: 'Each voice conversation holds one worker for its whole length, so this is also the most calls at once.', min: 1, max: 200),
                Actions::make([$this->restartQueueAction()])->key('restart-queue'),
            ])->summary(fn (): string => sprintf('Up to %d workers', array_sum($this->savedWorkers())).(($state = $this->horizonState()) !== null ? " · {$state}" : ''))
                ->tone(fn (): string => in_array($this->horizonState(), [self::NOT_RUNNING, self::RESTART_NEEDED], true) ? 'warning' : 'gray')
                ->advanced(),
            SettingsCard::make('programs', 'Programs', 'The programs on this server that read files and make PDFs. Enter a full path for a program that is not found.', Heroicon::OutlinedCommandLine, fn (): array => array_map(
                $this->programField(...),
                ServerProgram::cases(),
            ))->summary(fn (): string => $this->programsSummary())->advanced(),
            SettingsCard::make('development', __('Development'), __('Tools for finding problems in this install.'), Heroicon::OutlinedCodeBracket, fn (): array => [
                $this->toggle('logs_page_enabled', __('Logs page'),
                    help: __('Shows Logs in the sidebar, where admins read and clear the log files of this install. Off hides the page and blocks its address.')),
            ])->summary(fn (): string => $this->valueInUse('logs_page_enabled') === true ? __('Logs page on') : __('Logs page off'))
                ->advanced(),
        ];
    }

    /** Whether .env (TOOL_SWITCHES) leaves the tool of a recording setting in this install. */
    private function installed(string $property): bool
    {
        $tools = app(ToolRecording::class);

        return $property === 'pulse_enabled' ? $tools->pulseInstalled() : $tools->spectraInstalled();
    }

    /** A tool's recording switch, locked while .env leaves the tool out. */
    private function recordingToggle(string $property, string $label, ?string $help = null): Toggle
    {
        if (! $this->installed($property)) {
            return $this->toggle($property, $label, help: self::TOOL_SWITCHES[$property].'=false in .env leaves this tool out of the install, so it cannot record.')
                ->disabled();
        }

        return $this->toggle($property, $label, $help);
    }

    private function recordingSummary(string $property): string
    {
        return match (true) {
            ! $this->installed($property) => 'Off in .env',
            $this->valueInUse($property) === true => 'On',
            default => 'Off',
        };
    }

    /** Where a card's link opens the tool; none when the admin cannot open it. */
    private function toolUrl(StaffTool $tool): ?string
    {
        return $tool->unavailableReason() === null ? $tool->url() : null;
    }

    /**
     * The saved worker numbers (or the defaults), by Horizon supervisor.
     *
     * @return array<string, int>
     */
    private function savedWorkers(): array
    {
        return array_map(
            fn (string $property): int => (int) $this->valueInUse($property),
            self::WORKER_SETTINGS,
        );
    }

    /**
     * What the Queue workers card says about the queue (Horizon) after the
     * worker total; null when Horizon runs the saved numbers. Outside production Horizon
     * uses the numbers of its own environment in config/horizon.php, not
     * these, so the card says so rather than nothing.
     */
    private function horizonState(): ?string
    {
        if (! app()->isProduction()) {
            return self::ONLY_PRODUCTION;
        }

        $horizon = app(HorizonWorkers::class);

        return match (true) {
            $horizon->isStopping() => self::RESTARTING,
            ! $horizon->isRunning() => self::NOT_RUNNING,
            $horizon->differsFrom($this->savedWorkers()) => self::RESTART_NEEDED,
            default => null,
        };
    }

    /**
     * Restarts the queue (Horizon), so it uses the saved worker numbers.
     * Always shown, so the admin finds it; it works only in production while
     * Horizon runs (elsewhere nothing may start Horizon again), and its
     * tooltip says why it does not.
     */
    private function restartQueueAction(): Action
    {
        return Action::make('restart_queue')
            ->label('Restart queue')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color(fn (): string => $this->horizonState() === self::RESTART_NEEDED ? 'warning' : 'gray')
            ->disabled(fn (): bool => ! in_array($this->horizonState(), [null, self::RESTART_NEEDED], true))
            ->tooltip(fn (): ?string => match ($this->horizonState()) {
                self::ONLY_PRODUCTION => 'The queue uses these numbers only in production (APP_ENV=production). Here it uses the numbers in config/horizon.php.',
                self::NOT_RUNNING => 'The queue is not running. When it starts, it uses the saved numbers.',
                self::RESTARTING => 'The queue is restarting. It starts again once its running jobs finish.',
                default => null,
            })
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedArrowPath)
            ->modalHeading('Restart the queue?')
            ->modalDescription('The queue starts again with the saved worker numbers, so save your changes first. The jobs that are running now finish first, and new jobs wait until then. A live voice call or a long recording can make this take several minutes. Docker starts the queue again on its own; on another server, Supervisor or systemd must start Horizon again.')
            ->modalSubmitActionLabel('Restart queue')
            ->modalSubmitAction(fn (Action $action): Action => $action->color('warning'))
            ->action(function (): void {
                if (app(HorizonWorkers::class)->restart() === 0) {
                    Notification::make()->warning()->title('The queue is not running')->body('Start it, and it uses the saved worker numbers.')->send();

                    return;
                }

                Notification::make()->success()->title('The queue is restarting')->body('It starts again with the saved worker numbers once the running jobs finish.')->send();
            });
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
