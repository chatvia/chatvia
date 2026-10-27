<?php

declare(strict_types=1);

namespace App\Admin\Clusters\Settings\Pages;

use App\Admin\Clusters\Settings\AdminSettingsPage;
use App\Admin\Clusters\Settings\SettingsCard;
use App\Admin\Settings\Data\ConnectionCheckData;
use App\Admin\Settings\HorizonWorkers;
use App\Admin\Settings\PdfMaker;
use App\Admin\Settings\PdfMakerCheck;
use App\Admin\Settings\PlatformSettings;
use App\Admin\Settings\ServerProgram;
use App\Admin\Settings\ServerProgramFinder;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Install-wide options: the app's name, default language, the tracking code, visitor
 * location, alerts, what makes PDFs, the number of queue workers, where the
 * programs that read files are, and the panel's Logs page. The chat widget's
 * options are on Settings → Widget.
 */
class ManagePlatformSettings extends AdminSettingsPage
{
    /** The Horizon supervisors of config/horizon.php, and the setting that holds the most workers of each. */
    private const array WORKER_SETTINGS = [
        'chat' => 'chat_workers',
        'ingestion' => 'ingestion_workers',
        'analytics' => 'analytics_workers',
        'records' => 'records_workers',
        'media' => 'media_workers',
        'voice' => 'voice_workers',
        'generation' => 'generation_workers',
    ];

    /** What the Queue workers card says about the queue ({@see horizonState()}). */
    private const string NOT_RUNNING = 'Queue not running';

    private const string RESTARTING = 'Queue restarting';

    private const string RESTART_NEEDED = 'Restart needed';

    /** The settings of each PDF maker, as the card asks for them. */
    private const array PDF_MAKER_SETTINGS = [
        'cloudflare' => ['pdf_cloudflare_account_id', 'pdf_cloudflare_token'],
        'gotenberg' => ['pdf_gotenberg_url', 'pdf_gotenberg_username', 'pdf_gotenberg_password'],
    ];

    /** The settings a PDF maker cannot work without. */
    private const array PDF_MAKER_REQUIRED = [
        'cloudflare' => ['pdf_cloudflare_account_id', 'pdf_cloudflare_token'],
        'gotenberg' => ['pdf_gotenberg_url'],
    ];

    /** How the card's summary names a missing setting. */
    private const array PDF_MAKER_LABELS = [
        'pdf_cloudflare_account_id' => 'Account ID',
        'pdf_cloudflare_token' => 'API token',
        'pdf_gotenberg_url' => 'Address',
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
        return 'App name, language, tracking code, visitor location, PDF maker, queue workers, programs and logs.';
    }

    /**
     * The Development card shows or hides Logs in the sidebar, so the
     * sidebar is drawn again with the saved choice.
     */
    #[\Override]
    public function save(): void
    {
        parent::save();

        $this->dispatch('refresh-sidebar');
    }

    public static function overviewGroup(): string
    {
        return 'Platform and brand';
    }

    protected function showsValueInUse(string $property): bool
    {
        return $property === 'pdf_maker';
    }

    protected function cards(): array
    {
        return [
            SettingsCard::make('app_name', 'App name', 'The name people see in browser tabs, the dashboard, the admin panel and emails.', Heroicon::OutlinedTag, fn (): array => [
                $this->text('app_name', 'App name')->maxLength(60),
            ])->summary(fn (): string => (string) $this->valueInUse('app_name')),
            SettingsCard::make('language', 'Language', 'The language for people who have not chosen one, and for messaging channels.', Heroicon::OutlinedLanguage, fn (): array => [
                $this->choice('default_locale', 'Default language', $this->languages()),
            ])->summary(fn (): string => $this->languages()[(string) $this->valueInUse('default_locale')] ?? $this->shown($this->valueInUse('default_locale'))),
            SettingsCard::make('tracking', 'Tracking code', 'Code added to every page, such as the script of an analytics service. Nothing is added while it is empty.', Heroicon::OutlinedChartBar, fn (): array => [
                $this->code('tracking_code', 'Code',
                    help: 'Added to the dashboard, the hosted chat and the sign-in pages. It runs only on the live site, not on test copies.', placeholder: '<script defer src="https://…"></script>'),
            ])->summary(fn (): string => filled($this->valueInUse('tracking_code')) ? 'On' : 'Off'),
            SettingsCard::make('ip2location', 'IP2Location', 'Finds the country of a visitor from their IP address.', Heroicon::OutlinedMapPin, fn (): array => [
                $this->secret('ip2location_api_key', 'API key'),
            ])->advancedFields(fn (): array => [
                $this->url('ip2location_endpoint', 'Address'),
            ])->required('ip2location_api_key')->console('https://www.ip2location.io/dashboard'),
            SettingsCard::make('alerts', 'Alerts', 'Where the queue sends its alerts.', Heroicon::OutlinedBellAlert, fn (): array => [
                $this->email('horizon_notify_email', 'Alert email',
                    help: 'Gets an email when background jobs wait too long to start. A change applies after the queue restarts.'),
            ])->summary(fn (): string => filled($this->valueInUse('horizon_notify_email')) ? 'To '.$this->valueInUse('horizon_notify_email') : 'No alerts'),
            SettingsCard::make('pdf_maker', 'PDF maker', 'What makes PDFs: chat exports, and the PDF of an office file for its summary.', Heroicon::OutlinedDocumentArrowDown, fn (): array => [
                $this->pdfMakerChoice(),
                $this->pdfMakerField(PdfMaker::Cloudflare, $this->text('pdf_cloudflare_account_id', 'Account ID', help: 'Shown on the right side of the home page of your Cloudflare dashboard.'), required: true),
                $this->pdfMakerField(PdfMaker::Cloudflare, $this->secret('pdf_cloudflare_token', 'API token', help: 'Create a Cloudflare token with the "Browser Rendering: Edit" permission, then paste it here.', required: false)
                    ->hintAction(Action::make('getKey_pdf_cloudflare')
                        ->label('Get a token')
                        ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                        ->iconPosition('after')
                        ->url(PdfMaker::Cloudflare->keysUrl() ?? '', shouldOpenInNewTab: true)), required: true),
                $this->pdfMakerField(PdfMaker::Gotenberg, $this->url('pdf_gotenberg_url', 'Address', help: 'Where this server reaches your Gotenberg server, such as http://gotenberg:3000.'), required: true),
                $this->pdfMakerField(PdfMaker::Gotenberg, $this->text('pdf_gotenberg_username', 'Username', help: 'Fill in only if your Gotenberg server asks for a username and password.'), required: false),
                $this->pdfMakerField(PdfMaker::Gotenberg, $this->secret('pdf_gotenberg_password', 'Password', required: false), required: false),
            ])->properties('pdf_maker', ...array_merge(...array_values(self::PDF_MAKER_SETTINGS)))
                ->summary(fn (): string => $this->pdfMakerSummary())
                ->tone(fn (): string => $this->pdfMakerMissing() === [] ? 'gray' : 'warning'),
            SettingsCard::make('queue_workers', 'Queue workers', 'How many background jobs run at once. Each worker uses about 80 MB of memory. Save, then restart the queue.', Heroicon::OutlinedCog8Tooth, fn (): array => [
                $this->integer('chat_workers', 'Chat', help: 'For replies on messaging channels, sending emails and other quick jobs.', min: 2, max: 200),
                $this->integer('ingestion_workers', 'Files and websites', help: 'For reading the files and websites you add as knowledge, and for exports.', min: 1, max: 200),
                $this->integer('analytics_workers', 'Analytics', help: 'For analyzing conversations with AI, testing agent answers and sending security events.', min: 1, max: 100),
                $this->integer('records_workers', 'Records', help: 'For saving analytics numbers and audit logs. These jobs are quick.', min: 1, max: 100),
                $this->integer('media_workers', 'Audio and video', help: 'For turning recordings into text. One recording can take a long time.', min: 1, max: 50),
                $this->integer('voice_workers', 'Live voice calls', help: 'Each voice call uses one worker until it ends, so this is also the most calls at once.', min: 1, max: 200),
                $this->integer('generation_workers', 'Pictures, audio and video', help: 'For the pictures, audio files and videos agents make in chats. Each one can take up to a few minutes.', min: 1, max: 100),
                Actions::make([$this->restartQueueAction()])->key('restart-queue'),
            ])->summary(fn (): string => sprintf('Up to %d workers', array_sum($this->savedWorkers())).(($state = $this->horizonState()) !== null ? " · {$state}" : ''))
                ->tone(fn (): string => in_array($this->horizonState(), [self::NOT_RUNNING, self::RESTART_NEEDED], true) ? 'warning' : 'gray')
                ->advanced(),
            SettingsCard::make('programs', 'Programs', 'The programs on this server that read files and make PDFs. Without some of them the app does the work itself. Enter a full path for a program that is not found.', Heroicon::OutlinedCommandLine, fn (): array => array_map(
                $this->programField(...),
                ServerProgram::cases(),
            ))->summary(fn (): string => $this->programsSummary())
                ->tone(fn (): string => $this->programsLosingWork() === [] ? 'gray' : 'warning')
                ->advanced(),
            SettingsCard::make('development', __('Development'), __('Tools for finding problems in this install.'), Heroicon::OutlinedCodeBracket, fn (): array => [
                $this->toggle('logs_page_enabled', __('Logs page'),
                    help: __('Shows Logs in the sidebar, where admins read and clear the log files of this install. Off hides the page and blocks its address.')),
            ])->summary(fn (): string => $this->valueInUse('logs_page_enabled') === true ? __('Logs page on') : __('Logs page off'))
                ->advanced(),
        ];
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
     * worker total; null when Horizon runs the saved numbers.
     */
    private function horizonState(): ?string
    {
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
     * Always shown and never greyed out: when there is nothing to restart,
     * the notification says why.
     */
    private function restartQueueAction(): Action
    {
        return Action::make('restart_queue')
            ->label('Restart queue')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color(fn (): string => $this->horizonState() === self::RESTART_NEEDED ? 'warning' : 'gray')
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedArrowPath)
            ->modalHeading('Restart the queue?')
            ->modalDescription('The queue starts again with the saved worker numbers, so save your changes first. The jobs that are running now finish first, and new jobs wait until then. A live voice call or a long recording can make this take several minutes.')
            ->modalSubmitActionLabel('Restart queue')
            ->modalSubmitAction(fn (Action $action): Action => $action->color('warning'))
            ->action(function (): void {
                if (app(HorizonWorkers::class)->isStopping()) {
                    Notification::make()->info()->title('The queue is already restarting')->body('It starts again with the saved worker numbers once the running jobs finish.')->send();

                    return;
                }

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
                $path = filled($value) ? Str::trim($value) : '';

                if ($path === '' || $path === $program->value) {
                    return;
                }

                if (! $program->acceptsPath($path)) {
                    $fail("Enter \"{$program->value}\", or a full path that ends in /{$program->value}, such as /usr/local/bin/{$program->value}.");
                } elseif (app(ServerProgramFinder::class)->find($path) === null) {
                    $fail("There is no program at {$path} on this server, or it cannot be run.");
                }
            })
            ->hint(fn (): string => match ($this->programState($program)) {
                'found' => 'Found',
                'not_needed' => 'Not found · not needed',
                default => 'Not found',
            })
            ->hintIcon(fn (): Heroicon => match ($this->programState($program)) {
                'found' => Heroicon::OutlinedCheckCircle,
                'not_needed' => Heroicon::OutlinedInformationCircle,
                default => Heroicon::OutlinedExclamationTriangle,
            })
            ->hintColor(fn (): string => match ($this->programState($program)) {
                'found' => 'success',
                'not_needed' => 'gray',
                default => 'warning',
            })
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

    /** What the program is for, then where it was found, or what stops working without it. */
    private function programHelp(ServerProgram $program): string
    {
        $found = $this->programPath($program);

        if ($found !== null) {
            return $program->purpose()." Uses {$found}.";
        }

        $loss = $program->lossWhenMissing();

        return $program->purpose().' '.($loss !== null ? "Without it: {$loss} " : '').'Click Detect or enter its full path.';
    }

    /**
     * 'found', 'not_needed' (not found, and nothing stops working without
     * it), or 'missing'.
     */
    private function programState(ServerProgram $program): string
    {
        return match (true) {
            $this->programPath($program) !== null => 'found',
            $program->lossWhenMissing() === null => 'not_needed',
            default => 'missing',
        };
    }

    /**
     * The programs that are not found and whose absence stops something.
     *
     * @return list<ServerProgram>
     */
    private function programsLosingWork(): array
    {
        return array_values(array_filter(ServerProgram::cases(), fn (ServerProgram $program): bool => $this->programState($program) === 'missing'));
    }

    /** The file this server runs for the program's path in use, or null when there is none. */
    private function programPath(ServerProgram $program): ?string
    {
        return app(ServerProgramFinder::class)->find((string) $this->valueInUse($program->property()));
    }

    private function programsSummary(): string
    {
        $names = fn (array $programs): string => implode(', ', array_map(fn (ServerProgram $program): string => $program->value, $programs));
        $missing = $this->programsLosingWork();
        $notNeeded = array_values(array_filter(ServerProgram::cases(), fn (ServerProgram $program): bool => $this->programState($program) === 'not_needed'));

        return match (true) {
            $missing === [] && $notNeeded === [] => 'All found',
            $missing === [] => 'Not found, not needed: '.$names($notNeeded),
            $notNeeded === [] => 'Not found: '.$names($missing),
            default => 'Not found: '.$names($missing).' · not needed: '.$names($notNeeded),
        };
    }

    /**
     * The maker choice. A new pick, or new values for it, is checked with a
     * one-line PDF as it is saved, and refused when that fails; an unchanged
     * one is not tried again.
     */
    private function pdfMakerChoice(): Select
    {
        $options = $this->pdfMakerOptions();

        return $this->choice('pdf_maker', 'Make PDFs with', $options)
            ->selectablePlaceholder(false)
            ->helperText(fn (Get $get): ?string => PdfMaker::tryFrom((string) $get('pdf_maker'))?->description())
            ->live()
            ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                $maker = PdfMaker::tryFrom((string) $value);

                // A value that is not offered fails the "in" rule.
                if (! $maker instanceof PdfMaker) {
                    return;
                }

                $picked = fn (string $property): mixed => filled($get($property)) ? $get($property) : $this->valueInUse($property);

                // Unchanged, or still missing what it needs (the field says so).
                if ($this->pdfMakerSetup($maker, $picked) === $this->pdfMakerSetup(PdfMaker::inUse(), $this->valueInUse(...))
                    || array_any(self::PDF_MAKER_REQUIRED[$maker->value] ?? [], fn (string $property): bool => blank($picked($property)))) {
                    return;
                }

                $result = $this->checkPdfMaker($maker, $picked);

                if (! $result->connected) {
                    $fail($result->message ?? "{$maker->label()} could not make a PDF.");
                }
            });
    }

    /** A setting of one maker, shown (and required) only while it is picked. A saved secret is not asked for again. */
    private function pdfMakerField(PdfMaker $maker, TextInput $field, bool $required): TextInput
    {
        $property = $field->getName();

        return $field
            ->visible(fn (Get $get): bool => $get('pdf_maker') === $maker->value)
            ->required(fn (Get $get): bool => $required
                && $get('pdf_maker') === $maker->value
                && (! PlatformSettings::isSecret($property) || ! $this->isSaved($property)));
    }

    /**
     * Each maker by name; WeasyPrint marked found or not.
     *
     * @return array<string, string>
     */
    private function pdfMakerOptions(): array
    {
        return collect(PdfMaker::cases())->mapWithKeys(fn (PdfMaker $maker): array => [
            $maker->value => match (true) {
                $maker !== PdfMaker::Weasyprint => $maker->label(),
                $this->programPath(ServerProgram::Weasyprint) !== null => "{$maker->label()} (found)",
                default => "{$maker->label()} (not found)",
            },
        ])->all();
    }

    /**
     * What a maker uses, to tell whether a pick changed: the maker and its
     * own settings.
     *
     * @param  Closure(string): mixed  $value  A setting's value, by property.
     * @return list<string>
     */
    private function pdfMakerSetup(PdfMaker $maker, Closure $value): array
    {
        return [$maker->value, ...array_map(fn (string $property): string => (string) $value($property), self::PDF_MAKER_SETTINGS[$maker->value] ?? [])];
    }

    /**
     * Makes a test PDF with a maker's values.
     *
     * @param  Closure(string): mixed  $value  A setting's value, by property.
     */
    private function checkPdfMaker(PdfMaker $maker, Closure $value): ConnectionCheckData
    {
        $check = app(PdfMakerCheck::class);

        return match ($maker) {
            PdfMaker::Weasyprint => $check->weasyprint((string) $this->valueInUse(ServerProgram::Weasyprint->property())),
            PdfMaker::Cloudflare => $check->cloudflare((string) $value('pdf_cloudflare_account_id'), (string) $value('pdf_cloudflare_token')),
            PdfMaker::Gotenberg => $check->gotenberg((string) $value('pdf_gotenberg_url'), (string) $value('pdf_gotenberg_username'), (string) $value('pdf_gotenberg_password')),
        };
    }

    /**
     * What the maker in use still lacks: the weasyprint program, or the
     * service's required settings.
     *
     * @return list<string>
     */
    private function pdfMakerMissing(): array
    {
        return match (PdfMaker::inUse()) {
            PdfMaker::Weasyprint => $this->programPath(ServerProgram::Weasyprint) === null ? ['the weasyprint program'] : [],
            PdfMaker::Cloudflare, PdfMaker::Gotenberg => array_values(array_map(
                fn (string $property): string => self::PDF_MAKER_LABELS[$property],
                array_filter(self::PDF_MAKER_REQUIRED[PdfMaker::inUse()->value], fn (string $property): bool => blank($this->valueInUse($property))),
            )),
        };
    }

    private function pdfMakerSummary(): string
    {
        $maker = PdfMaker::inUse();
        $missing = $this->pdfMakerMissing();

        return match (true) {
            $maker === PdfMaker::Weasyprint => $missing === [] ? 'WeasyPrint on this server' : 'WeasyPrint · not found on this server',
            $missing !== [] => $maker->label().' · missing: '.implode(', ', $missing),
            $maker === PdfMaker::Gotenberg => 'Gotenberg at '.$this->valueInUse('pdf_gotenberg_url'),
            default => $maker->label(),
        };
    }

    /** @return array<string, string> */
    private function languages(): array
    {
        return Arr::except(config('chatvia.languages'), 'auto');
    }
}
