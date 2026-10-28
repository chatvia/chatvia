<?php

declare(strict_types=1);

namespace App\Admin\Clusters\Settings\Pages;

use App\Admin\Clusters\Settings\AdminSettingsPage;
use App\Admin\Clusters\Settings\SettingsCard;
use App\Admin\Forms\CardRadio;
use App\Admin\Settings\StorageCheck;
use App\Admin\Settings\StorageDisks;
use App\Admin\Settings\StorageSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Where the app keeps its files: the private files, the public images and
 * the backups each on this server or in a bucket of S3-compatible object
 * storage (AWS S3, Scaleway, Cloudflare R2, MinIO, ...), each with its own
 * connection, so it may sit with another service or account. A button checks
 * the saved storage by writing, reading and deleting a small file on each
 * disk. Switching moves no files, so the page says so.
 */
class ManageStorageSettings extends AdminSettingsPage
{
    /** What each bucket's connection holds, after its name. */
    private const array CONNECTION = ['endpoint', 'region', 'key', 'secret', 'path_style'];

    private const array DRIVERS = [
        'local' => 'This server',
        's3' => 'S3-compatible storage',
    ];

    #[\Override]
    protected static string $settings = StorageSettings::class;

    #[\Override]
    protected static ?string $title = 'Storage';

    #[\Override]
    protected static ?string $slug = 'storage';

    #[\Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    #[\Override]
    protected static ?int $navigationSort = 13;

    public static function description(): string
    {
        return 'Where files are kept: this server or S3-compatible buckets.';
    }

    public static function overviewGroup(): string
    {
        return 'Platform and brand';
    }

    public function overviewStatus(): array
    {
        if ($this->valueInUse('driver') !== 's3') {
            return filled($this->valueInUse('s3_public_bucket')) || filled($this->valueInUse('s3_backup_bucket'))
                ? ['S3-compatible storage', 'success']
                : ['On this server', 'gray'];
        }

        return ['S3-compatible storage', filled($this->valueInUse('s3_private_bucket')) ? 'success' : 'warning'];
    }

    protected function showsValueInUse(string $property): bool
    {
        return $property === 'driver';
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('testStorage')
                ->label('Test the connection')
                ->icon(Heroicon::OutlinedSignal)
                ->color('gray')
                ->modalDescription('Writes a small file with the saved settings, reads it back and deletes it. Save your changes first.')
                ->modalSubmitActionLabel('Test')
                ->action(function (): void {
                    $failures = array_filter(array_map($this->checkDisk(...), StorageDisks::DISKS));

                    if ($failures === []) {
                        Notification::make()->success()->title('Storage works')->body('Files were written, read and deleted.')->send();

                        return;
                    }

                    Notification::make()->danger()->title('Storage does not work')->body(Arr::join($failures, "\n"))->persistent()->send();
                }),
        ];
    }

    protected function cards(): array
    {
        return [
            SettingsCard::make('location', 'Where files are kept', 'Knowledge files, chat attachments, chunk images, generated media, public images and backups.', Heroicon::OutlinedArchiveBox, fn (): array => [
                Callout::make('We recommend S3-compatible storage')
                    ->description('Files on this server are lost if its disk breaks, and other servers cannot reach them. A bucket keeps them safe and lets the app grow to more servers.')
                    ->icon(Heroicon::OutlinedLightBulb)
                    ->info()
                    ->visible(fn (Get $get): bool => ! $this->usesS3($get) || $get('public_files') !== 'bucket' || $get('backup_files') !== 'bucket'),
                Callout::make('Switching moves no files')
                    ->description('Files already stored stay where they are, and the app no longer finds them. Switch before people add files, or copy the files to the new place first.')
                    ->icon(Heroicon::OutlinedExclamationTriangle)
                    ->warning(),
                Fieldset::make('Private files')
                    ->columns(2)
                    ->schema([
                        $this->explanation('Knowledge files, chat attachments, chunk images and generated media.'),
                        CardRadio::make('driver')
                            ->label('Keep private files on')
                            ->options(['local' => 'This server', 's3' => 'S3-compatible private bucket'])
                            ->descriptions([
                                'local' => 'Private files are stored on this server.',
                                's3' => 'AWS S3, Cloudflare R2, MinIO and others.',
                            ])
                            ->icons(['local' => Heroicon::OutlinedServer, 's3' => asset('images/logos/services/aws.svg')])
                            ->in(array_keys(self::DRIVERS))
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set): void {
                                if (! $this->usesS3($get) && $get('backup_files') === 'private') {
                                    $set('backup_files', 'local');
                                }
                            })
                            ->columnSpanFull(),
                        ...$this->bucketFields('private', 'Private bucket', 'For example chatvia-files.'),
                    ]),
                Fieldset::make('Public images')
                    ->columns(2)
                    ->schema([
                        $this->explanation('Profile photos and agent logos. A bucket for them must be public.'),
                        CardRadio::make('public_files')
                            ->label('Keep profile photos and agent logos on')
                            ->options(['local' => 'This server', 'bucket' => 'S3-compatible public bucket'])
                            ->descriptions([
                                'local' => 'Public images are stored on this server.',
                                'bucket' => 'A bucket anyone can read.',
                            ])
                            ->icons(['local' => Heroicon::OutlinedServer, 'bucket' => asset('images/logos/services/aws.svg')])
                            ->afterStateHydrated(fn (CardRadio $component): CardRadio => $component->state(filled($this->valueInUse('s3_public_bucket')) ? 'bucket' : 'local'))
                            ->live()
                            ->columnSpanFull(),
                        ...$this->bucketFields('public', 'Public bucket', 'Turn on public read access for this bucket.'),
                    ]),
                Fieldset::make('Backups')
                    ->columns(2)
                    ->schema([
                        $this->explanation('Backups of the database and Redis. A bucket for them must be private.'),
                        CardRadio::make('backup_files')
                            ->label('Keep backups on')
                            ->options(['local' => 'This server', 'bucket' => 'S3-compatible private bucket', 'private' => 'Same as the private bucket'])
                            ->descriptions(fn (Get $get): array => [
                                'local' => 'Backups are stored on this server.',
                                'bucket' => 'Safe if this server breaks.',
                                'private' => $this->usesS3($get) ? 'Uses the bucket and keys of the private files.' : 'Keep private files in a bucket first.',
                            ])
                            ->icons(['local' => Heroicon::OutlinedServer, 'bucket' => asset('images/logos/services/aws.svg'), 'private' => Heroicon::OutlinedDocumentDuplicate])
                            ->disableOptionWhen(fn (string $value, Get $get): bool => $value === 'private' && ! $this->usesS3($get))
                            ->afterStateHydrated(fn (CardRadio $component): CardRadio => $component->state(match (true) {
                                app(StorageSettings::class)->backupsInPrivateBucket() => 'private',
                                filled($this->valueInUse('s3_backup_bucket')) => 'bucket',
                                default => 'local',
                            }))
                            ->live()
                            ->afterStateUpdated($this->copyPrivateBucket(...))
                            ->columnSpanFull(),
                        ...$this->bucketFields('backup', 'Backups bucket', 'For example chatvia-backups.'),
                    ]),
            ])
                ->properties('driver', ...$this->bucketProperties())
                ->summary(fn (): string => self::DRIVERS[(string) $this->valueInUse('driver')] ?? self::DRIVERS['local']),
        ];
    }

    /** Whether the form stores files in S3-compatible storage. */
    private function usesS3(Get $get): bool
    {
        return ($get('driver') ?? $this->valueInUse('driver')) === 's3';
    }

    /**
     * One bucket's name and connection. Each bucket has its own, so it may
     * sit with another service or account; the public one also asks for the
     * URL that serves its files.
     *
     * @return list<Component>
     */
    private function bucketFields(string $bucket, string $label, string $help): array
    {
        $used = fn (Get $get): bool => $this->usesBucket($get, $bucket);
        $copied = fn (Get $get): bool => $bucket === 'backup' && $get('backup_files') === 'private';
        $shown = fn (Get $get): bool => $used($get) || $copied($get);

        // The backups bucket follows the private one while the backups go
        // there: its fields show the private bucket's and are locked.
        $linked = fn (Field $field): Field => match ($bucket) {
            'private' => $field->live(onBlur: $field instanceof TextInput)->afterStateUpdated($this->copyPrivateBucket(...)),
            'backup' => $field->disabled($copied),
            default => $field,
        };

        return Arr::map([
            $this->text("s3_{$bucket}_bucket", $label, help: $help)
                ->when($bucket === 'public', fn (TextInput $input): TextInput => $input
                    ->different('s3_private_bucket')
                    ->different('s3_backup_bucket')
                    ->validationMessages(['different' => 'Use a bucket of its own: anyone can read the files in the public bucket.']))
                ->required($used)
                ->visible($shown)
                ->columnSpan($bucket === 'public' ? 1 : 'full'),
            ...($bucket === 'public' ? [
                $this->url('s3_public_url', 'Public URL', help: 'The address that serves the public bucket\'s files, for example https://pub-1234.r2.dev or your CDN.')
                    ->required($used)
                    ->visible($used),
            ] : []),
            $this->url("s3_{$bucket}_endpoint", 'Endpoint', help: 'The address of your storage service, for example https://s3.fr-par.scw.cloud. Leave it empty for AWS S3.')
                ->visible($shown),
            $this->text("s3_{$bucket}_region", 'Region', help: 'Where the bucket is kept, for example eu-west-1 or fr-par. Use auto for Cloudflare R2.')
                ->required($used)
                ->visible($shown),
            $this->text("s3_{$bucket}_key", 'Access key ID')
                ->required($used)
                ->visible($shown),
            $this->secret("s3_{$bucket}_secret", 'Secret access key')
                ->required(fn (Get $get): bool => $used($get) && ! $this->isSaved("s3_{$bucket}_secret"))
                ->visible($shown),
            $this->toggle("s3_{$bucket}_path_style", 'Use path-style addresses',
                help: 'Turn this on if your storage server needs it, as MinIO does. Most services, like AWS S3, work with it off.')
                ->visible($shown)
                ->columnSpanFull(),
        ], $linked);
    }

    /**
     * Fill the backups bucket's fields with the private bucket's while the
     * backups go to the private bucket, so the locked fields show what is
     * used. Saving copies the saved values too ({@see valuesToSave()}).
     */
    private function copyPrivateBucket(Get $get, Set $set): void
    {
        if ($get('backup_files') !== 'private') {
            return;
        }

        foreach (['bucket', ...self::CONNECTION] as $field) {
            $set("s3_backup_{$field}", $get("s3_private_{$field}"));
        }
    }

    /** A line of text that says what a part of the card is for. */
    private function explanation(string $text): Text
    {
        return Text::make($text)->color('gray')->columnSpanFull();
    }

    /**
     * Every setting of the three buckets.
     *
     * @return list<string>
     */
    private function bucketProperties(): array
    {
        return collect(['private', 'public', 'backup'])
            ->flatMap(fn (string $bucket): array => [
                "s3_{$bucket}_bucket",
                ...($bucket === 'public' ? ['s3_public_url'] : []),
                ...collect(self::CONNECTION)->map(fn (string $field): string => "s3_{$bucket}_{$field}"),
            ])
            ->all();
    }

    /** Whether the form keeps files in this bucket of S3-compatible storage. */
    private function usesBucket(Get $get, string $bucket): bool
    {
        return match ($bucket) {
            'public' => $get('public_files') === 'bucket',
            'backup' => $get('backup_files') === 'bucket',
            default => $this->usesS3($get),
        };
    }

    /**
     * The "keep … on" choices of public images and backups are no settings:
     * picking this server for them forgets the bucket named for them and its
     * connection, and keeping backups in the private bucket saves a copy of
     * its name and connection (a blank secret: the saved one) as theirs.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    #[\Override]
    protected function valuesToSave(array $data): array
    {
        $values = parent::valuesToSave($data);

        $backupsInPrivate = ($data['backup_files'] ?? null) === 'private' && ($data['driver'] ?? null) === 's3';

        foreach (['public' => 'public_files', 'backup' => 'backup_files'] as $bucket => $choice) {
            if ($bucket === 'backup' && $backupsInPrivate) {
                foreach (['bucket', ...self::CONNECTION] as $field) {
                    $values["s3_backup_{$field}"] = array_key_exists("s3_private_{$field}", $values)
                        ? $values["s3_private_{$field}"]
                        : app(StorageSettings::class)->{"s3_private_{$field}"};
                }

                continue;
            }

            if (array_key_exists($choice, $data) && $data[$choice] !== 'bucket') {
                foreach ($this->bucketProperties() as $property) {
                    if (Str::startsWith($property, "s3_{$bucket}_")) {
                        $values[$property] = null;
                    }
                }
            }
        }

        return $values;
    }

    /**
     * Write, read and delete a small file on a disk with the saved settings,
     * and load it from the public URL when public images are in a bucket;
     * why it failed, or null.
     */
    private function checkDisk(string $disk): ?string
    {
        $config = (array) config("filesystems.disks.{$disk}");
        $publicUrl = $disk === 'assets' && ($config['driver'] ?? null) === 's3' ? ($config['url'] ?? null) : null;

        try {
            $storage = Storage::disk($disk);
        } catch (Throwable $e) {
            return "The {$disk} storage failed: ".Str::limit($e->getMessage(), 300);
        }

        return app(StorageCheck::class)->checkDisk($storage, "The {$disk} storage", $publicUrl);
    }
}
