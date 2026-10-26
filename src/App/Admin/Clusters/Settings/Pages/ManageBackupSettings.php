<?php

declare(strict_types=1);

namespace App\Admin\Clusters\Settings\Pages;

use App\Admin\Backup\Actions\DeleteBackupAction;
use App\Admin\Backup\Actions\StartBackupAction;
use App\Admin\Backup\Data\PublicKeyData;
use App\Admin\Backup\Enums\BackupFrequency;
use App\Admin\Backup\Enums\BackupPart;
use App\Admin\Backup\Enums\BackupProgram;
use App\Admin\Backup\Enums\BackupStatus;
use App\Admin\Backup\Enums\BackupTrigger;
use App\Admin\Backup\Exceptions\InvalidPublicKeyException;
use App\Admin\Backup\Models\Backup;
use App\Admin\Backup\Services\BackupEncryption;
use App\Admin\Backup\Services\BackupSchedule;
use App\Admin\Clusters\Settings\AdminSettingsPage;
use App\Admin\Clusters\Settings\SettingsCard;
use App\Admin\Settings\BackupSettings;
use App\Admin\Settings\ServerProgramFinder;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Settings → Backups: whether the database and Redis are backed up on a
 * schedule and how often, how many backups stay, the OpenPGP public key they
 * are encrypted to, what else goes in, and where the programs a backup runs
 * are. Lists the backups made, to download or delete one, and makes one now.
 *
 * The key is checked with gpg when it is saved, so a key that cannot
 * encrypt (a private key, an expired or sign-only one) is never saved and
 * no scheduled backup fails on it later.
 */
class ManageBackupSettings extends AdminSettingsPage implements HasTable
{
    use InteractsWithTable;

    #[\Override]
    protected static string $settings = BackupSettings::class;

    #[\Override]
    protected static ?string $title = 'Backups';

    #[\Override]
    protected static ?string $slug = 'backups';

    #[\Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    #[\Override]
    protected static ?int $navigationSort = 14;

    public static function description(): string
    {
        return 'Back up the database and Redis on a schedule, encrypted with your key.';
    }

    public static function overviewGroup(): string
    {
        return 'Platform and brand';
    }

    public function overviewStatus(): array
    {
        $last = Backup::query()->whereIn('status', [BackupStatus::Completed, BackupStatus::Failed])->latest('id')->first();

        return match (true) {
            $last?->status === BackupStatus::Failed => ['The last backup failed', 'danger'],
            ! $this->valueInUse('enabled') => ['Automatic backups are off', 'warning'],
            default => [$this->frequency()->label(), 'success'],
        };
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [$this->backUpNowAction()];
    }

    protected function cards(): array
    {
        return [
            SettingsCard::make('schedule', 'Automatic backups', 'Back up the database and Redis on a schedule, and keep the newest ones.', Heroicon::OutlinedClock, fn (): array => [
                $this->toggle('enabled', 'Back up automatically',
                    help: 'Makes a backup on the schedule below. Off makes backups only when you click "Back up now".'),
                $this->choice('frequency', 'How often', $this->frequencies(),
                    help: 'The time between two automatic backups. A backup that failed is tried again after at most 6 hours.'),
                $this->integer('keep', 'Backups to keep', min: 1, max: 365,
                    help: 'The newest backups that stay. An older one is deleted when a new backup is done.'),
                Callout::make(fn (): string => 'Not found: '.collect($this->missingPrograms())->map(fn (BackupProgram $program): string => $program->value)->implode(', '))
                    ->description('Backups fail until it is installed, or its full path is entered under Programs (Advanced).')
                    ->icon(Heroicon::OutlinedExclamationTriangle)
                    ->danger()
                    ->visible(fn (): bool => $this->missingPrograms() !== []),
                Callout::make('Backups are kept on this server')
                    ->description('If this server\'s disk breaks, the backups are lost with it. Keep files in S3-compatible storage (Settings → Storage), or download the backups and keep them somewhere else.')
                    ->icon(Heroicon::OutlinedExclamationTriangle)
                    ->warning()
                    ->visible(fn (): bool => config('filesystems.storage') !== 's3'),
            ])
                ->properties('enabled', 'frequency', 'keep')
                ->summary(fn (): string => $this->scheduleSummary()),

            SettingsCard::make('encryption', 'Encryption', 'Encrypt every backup with your OpenPGP public key. Only your private key can open it.', Heroicon::OutlinedLockClosed, fn (): array => [
                $this->code('public_key', 'Public key',
                    help: 'Your OpenPGP public key, as gpg --armor --export you@example.com prints it. Never paste your private key: the server only needs the public one.',
                    placeholder: "-----BEGIN PGP PUBLIC KEY BLOCK-----\n…\n-----END PGP PUBLIC KEY BLOCK-----")
                    // A key is many lines long: six rows that scroll.
                    ->autosize(false)
                    ->rows(6)
                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        if (blank($value)) {
                            return;
                        }

                        try {
                            app(BackupEncryption::class)->inspect((string) $value);
                        } catch (InvalidPublicKeyException $e) {
                            $fail($e->getMessage());
                        }
                    }),
                Callout::make(fn (): string => 'Encrypted to '.($this->savedKey()?->userId ?? 'your key'))
                    ->description(fn (): string => $this->keyDescription())
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->success()
                    ->visible(fn (): bool => $this->savedKey() instanceof PublicKeyData),
                Callout::make('Backups are not encrypted')
                    ->description('Anyone who gets a backup file can read it. So the app key is left out: keep it safe yourself (APP_KEY), or values encrypted with it cannot be read after a restore.')
                    ->icon(Heroicon::OutlinedExclamationTriangle)
                    ->warning()
                    ->visible(fn (): bool => blank($this->valueInUse('public_key'))),
            ])
                ->properties('public_key')
                ->summary(fn (): string => filled($this->valueInUse('public_key')) ? 'Encrypted' : 'Not encrypted'),

            SettingsCard::make('list', 'Saved backups', 'The backups made so far, newest first. Download one to keep it somewhere else.', Heroicon::OutlinedArchiveBox, fn (): array => [
                EmbeddedTable::make(),
            ])
                ->properties()
                ->summary(fn (): string => $this->listSummary()),

            SettingsCard::make('contents', 'What goes in a backup', 'The database, the analytics database and Redis always go in. Add the uploaded files and the logs of the staff tools.', Heroicon::OutlinedSquare3Stack3d, fn (): array => [
                $this->toggle('include_files', 'Add the uploaded files',
                    help: 'Knowledge files, chat attachments and images, when they are kept on this server. Backups can get much larger. With S3-compatible storage the files are already kept off this server, so they are left out.')
                    ->disabled(fn (): bool => config('filesystems.storage') === 's3'),
                $this->toggle('include_tool_databases', 'Add the Pulse and Spectra databases',
                    help: 'The server statistics (Pulse) and the log of AI calls (Spectra), when they have their own databases.'),
            ])
                ->properties('include_files', 'include_tool_databases')
                ->advanced(),

            SettingsCard::make('programs', 'Programs', 'The programs on this server that make the backups. Enter a full path for a program that is not found.', Heroicon::OutlinedCommandLine, fn (): array => array_map(
                $this->programField(...),
                BackupProgram::cases(),
            ))
                ->properties(...array_map(fn (BackupProgram $program): string => $program->property(), BackupProgram::cases()))
                ->summary(fn (): string => $this->missingPrograms() === [] ? 'All found' : 'Not found: '.collect($this->missingPrograms())->map(fn (BackupProgram $program): string => $program->value)->implode(', '))
                ->tone(fn (): string => $this->missingPrograms() === [] ? 'gray' : 'warning')
                ->advanced(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Backup::query()->latest('id'))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Made')
                    ->dateTime('Y-m-d H:i')
                    ->description(fn (Backup $backup): string => $backup->trigger === BackupTrigger::Manual ? 'By hand' : 'On schedule'),
                TextColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn (BackupStatus $state): string => match ($state) {
                        BackupStatus::Pending => 'Waiting',
                        BackupStatus::Running => 'Running',
                        BackupStatus::Completed => 'Done',
                        BackupStatus::Failed => 'Failed',
                    })
                    ->badge()
                    ->color(fn (BackupStatus $state): string => match ($state) {
                        BackupStatus::Pending, BackupStatus::Running => 'info',
                        BackupStatus::Completed => 'success',
                        BackupStatus::Failed => 'danger',
                    })
                    ->description(fn (Backup $backup): ?string => $backup->status === BackupStatus::Failed ? Str::limit((string) Str::of((string) $backup->error)->before("\n"), 60) : null)
                    ->tooltip(fn (Backup $backup): ?string => $backup->status === BackupStatus::Failed ? $backup->error : null)
                    ->wrap(),
                TextColumn::make('parts')
                    ->label('Holds')
                    ->state(fn (Backup $backup): array => $this->partLabels($backup))
                    ->badge()
                    ->color('gray')
                    ->wrap()
                    ->placeholder('—'),
                TextColumn::make('size')
                    ->label('Size')
                    ->formatStateUsing(fn (?int $state): string => Number::fileSize((int) $state, precision: 1))
                    ->placeholder('—'),
                TextColumn::make('key_fingerprint')
                    ->label('Encrypted')
                    ->state(fn (Backup $backup): string => $backup->isEncrypted() ? 'Yes' : 'No')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Yes' ? 'success' : 'gray')
                    ->tooltip(fn (Backup $backup): ?string => $backup->key_fingerprint),
            ])
            ->headerActions([$this->backUpNowAction('backUpNowInList')])
            ->recordActions([$this->downloadAction(), $this->deleteAction()])
            ->emptyStateHeading('No backups yet')
            ->emptyStateDescription('Make the first one with "Back up now".')
            ->emptyStateIcon(Heroicon::OutlinedArchiveBox)
            // Keeps a backup's status up to date while one is being made.
            ->poll(fn (): ?string => Backup::query()->whereIn('status', [BackupStatus::Pending, BackupStatus::Running])->exists() ? '5s' : null)
            ->defaultPaginationPageOption(10);
    }

    /**
     * The path of a program. Blank runs it by name, from the PATH. Only its
     * name or a full path that ends in it is accepted ({@see BackupProgram}),
     * and a full path must lead to a program on this server.
     */
    private function programField(BackupProgram $program): TextInput
    {
        return $this->text($program->property(), $program->value, help: $this->programHelp($program))
            ->placeholder($program->value)
            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail) use ($program): void {
                $path = (string) $value;

                if (blank($path)) {
                    return;
                }

                if (! $program->acceptsPath($path)) {
                    $fail("Enter {$program->value}, or a full path that ends in /{$program->value}.");
                } elseif (app(ServerProgramFinder::class)->find($path) === null) {
                    $fail("There is no {$program->value} program at {$path}.");
                }
            });
    }

    private function programHelp(BackupProgram $program): string
    {
        $found = $this->programPath($program);

        return $program->purpose().($found !== null ? " Uses {$found}." : ' Not found: enter its full path.');
    }

    private function programPath(BackupProgram $program): ?string
    {
        return app(ServerProgramFinder::class)->find((string) ($this->valueInUse($program->property()) ?: $program->value));
    }

    /**
     * The programs backups need with the settings in use that are not found.
     *
     * @return list<BackupProgram>
     */
    private function missingPrograms(): array
    {
        return once(fn (): array => array_values(array_filter(
            BackupProgram::cases(),
            fn (BackupProgram $program): bool => $program->isNeeded() && $this->programPath($program) === null,
        )));
    }

    private function backUpNowAction(string $name = 'backUpNow'): Action
    {
        return Action::make($name)
            ->label('Back up now')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->modalHeading('Back up now?')
            ->modalDescription('A worker makes the backup with the saved settings. It shows in the list below; a large database takes a while.')
            ->modalSubmitActionLabel('Back up now')
            ->action(function (): void {
                app(StartBackupAction::class)->execute();

                Notification::make()->success()->title('Backup started')->body('It shows as done in the list when it is ready.')->send();
            });
    }

    private function downloadAction(): Action
    {
        return Action::make('download')
            ->label('Download')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->iconButton()
            ->tooltip('Download')
            ->color('gray')
            ->visible(fn (Backup $record): bool => $record->status === BackupStatus::Completed && filled($record->path))
            ->action(fn (Backup $record): StreamedResponse => Storage::disk((string) $record->disk)->download((string) $record->path, Str::afterLast((string) $record->path, '/')));
    }

    private function deleteAction(): Action
    {
        return Action::make('delete')
            ->label('Delete')
            ->icon(Heroicon::OutlinedTrash)
            ->iconButton()
            ->tooltip('Delete')
            ->color('danger')
            ->visible(fn (Backup $record): bool => $record->status !== BackupStatus::Running)
            ->requiresConfirmation()
            ->modalHeading('Delete this backup?')
            ->modalDescription('The backup file is deleted for good. It cannot be restored afterwards.')
            ->modalSubmitActionLabel('Delete')
            ->action(function (Backup $record): void {
                try {
                    app(DeleteBackupAction::class)->execute($record);
                } catch (InvalidArgumentException $e) {
                    Notification::make()->warning()->title('Not deleted')->body($e->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title('Backup deleted')->send();
            });
    }

    /**
     * @return array<string, string>
     */
    private function frequencies(): array
    {
        return collect(BackupFrequency::cases())
            ->mapWithKeys(fn (BackupFrequency $frequency): array => [$frequency->value => $frequency->label()])
            ->all();
    }

    private function frequency(): BackupFrequency
    {
        return BackupFrequency::tryFrom((string) $this->valueInUse('frequency')) ?? BackupFrequency::Daily;
    }

    private function scheduleSummary(): string
    {
        if (! $this->valueInUse('enabled')) {
            return 'Off';
        }

        $next = app(BackupSchedule::class)->nextDueAt();
        $keep = (int) $this->valueInUse('keep');

        return $this->frequency()->label().', keeps '.$keep.' · next '.($next === null || $next->isPast() ? 'within the hour' : $next->format('Y-m-d H:i'));
    }

    private function listSummary(): string
    {
        $count = Backup::query()->where('status', BackupStatus::Completed)->count();

        return $count === 0 ? 'None yet' : $count.' '.Str::plural('backup', $count);
    }

    /** The saved public key as gpg reads it; null when none is saved or it cannot be read. */
    private function savedKey(): ?PublicKeyData
    {
        $key = (string) $this->valueInUse('public_key');

        if (blank($key)) {
            return null;
        }

        return once(function () use ($key): ?PublicKeyData {
            try {
                return app(BackupEncryption::class)->inspect($key);
            } catch (InvalidPublicKeyException) {
                return null;
            }
        });
    }

    private function keyDescription(): string
    {
        $key = $this->savedKey();

        if (! $key instanceof PublicKeyData) {
            return '';
        }

        return collect([
            'Fingerprint '.Str::of($key->fingerprint)->split(4)->implode(' '),
            $key->expiresAt === null ? 'Does not expire' : 'Expires '.$key->expiresAt->format('Y-m-d'),
            'Each backup also holds the app key, so a restore can read every saved secret.',
        ])->implode(' · ');
    }

    /**
     * @return list<string>
     */
    private function partLabels(Backup $backup): array
    {
        return ($backup->parts ?? collect())
            ->map(fn (BackupPart $part): string => match ($part) {
                BackupPart::Database => 'Database',
                BackupPart::Secrets => 'App key',
                BackupPart::Redis => 'Redis',
                BackupPart::Files => 'Files',
                BackupPart::Analytics => 'Analytics',
                BackupPart::Pulse => 'Pulse',
                BackupPart::Spectra => 'Spectra',
            })
            ->values()
            ->all();
    }
}
