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
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Where the app keeps its files: on this server, or in S3-compatible object
 * storage (AWS S3, Scaleway, Cloudflare R2, MinIO, ...) with a private
 * bucket and optional public and backups buckets, each with its own
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
        return 'Where files are kept: this server or S3-compatible storage.';
    }

    public static function overviewGroup(): string
    {
        return 'Platform and brand';
    }

    public function overviewStatus(): array
    {
        if ($this->valueInUse('driver') !== 's3') {
            return ['On this server', 'gray'];
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
        $s3 = fn (Get $get): bool => $this->usesS3($get);

        return [
            SettingsCard::make('location', 'Where files are kept', 'Knowledge files, chat attachments, chunk images, generated media, public images and backups.', Heroicon::OutlinedArchiveBox, fn (): array => [
                $this->choice('driver', 'Store files in', self::DRIVERS,
                    help: 'This server needs no setup. S3-compatible storage keeps files with an online service, such as AWS S3 or Cloudflare R2, and grows with them.')
                    ->live(),
                Callout::make('Switching moves no files')
                    ->description('Files already stored stay where they are, and the app no longer finds them. Switch before people add files, or copy the files to the new place first.')
                    ->icon(Heroicon::OutlinedExclamationTriangle)
                    ->warning(),
                Fieldset::make('Private files')
                    ->columns(2)
                    ->visible($s3)
                    ->schema([
                        $this->explanation('Knowledge files, chat attachments, chunk images and generated media. Keep this bucket private: the app opens its files only through short-lived signed links.'),
                        ...$this->bucketFields('private', 'Private bucket', 'For example chatvia-files.'),
                    ]),
                Fieldset::make('Public images')
                    ->columns(2)
                    ->visible($s3)
                    ->schema([
                        $this->explanation('Profile photos and agent logos. Visitors\' browsers load them straight from where they are kept, so a bucket for them must be public.'),
                        CardRadio::make('public_files')
                            ->label('Keep profile photos and agent logos on')
                            ->options(['local' => 'This server', 'bucket' => 'A public bucket'])
                            ->descriptions([
                                'local' => 'This server keeps and serves them. Fine for one server.',
                                'bucket' => 'A bucket anyone can read. Needed when the app runs on more than one server.',
                            ])
                            ->icons(['local' => Heroicon::OutlinedServer, 'bucket' => Heroicon::OutlinedGlobeAlt])
                            ->afterStateHydrated(fn (CardRadio $component): CardRadio => $component->state(filled($this->valueInUse('s3_public_bucket')) ? 'bucket' : 'local'))
                            ->live()
                            ->columnSpanFull(),
                        ...$this->bucketFields('public', 'Public bucket', 'Turn on public read access for this bucket.'),
                    ]),
                Fieldset::make('Backups')
                    ->columns(2)
                    ->visible($s3)
                    ->schema([
                        $this->explanation('The backups of the database and Redis. They hold all your data, so keep their bucket private.'),
                        CardRadio::make('backup_files')
                            ->label('Keep backups in')
                            ->options(['private' => 'The private bucket', 'bucket' => 'A bucket of their own'])
                            ->descriptions([
                                'private' => 'Next to the private files. Nothing more to set up.',
                                'bucket' => 'Safer: the backups stay if the private bucket is deleted.',
                            ])
                            ->icons(['private' => Heroicon::OutlinedLockClosed, 'bucket' => Heroicon::OutlinedArchiveBox])
                            ->afterStateHydrated(fn (CardRadio $component): CardRadio => $component->state(filled($this->valueInUse('s3_backup_bucket')) ? 'bucket' : 'private'))
                            ->live()
                            ->columnSpanFull(),
                        ...$this->bucketFields('backup', 'Backups bucket', 'For example chatvia-backups.'),
                    ]),
            ])
                ->properties('driver', ...self::bucketProperties())
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

        return [
            $this->text("s3_{$bucket}_bucket", $label, help: $help)
                ->when($bucket === 'public', fn (TextInput $input): TextInput => $input
                    ->different('s3_private_bucket')
                    ->different('s3_backup_bucket')
                    ->validationMessages(['different' => 'Use a bucket of its own: anyone can read the files in the public bucket.']))
                ->required($used)
                ->visible($used)
                ->columnSpan($bucket === 'public' ? 1 : 'full'),
            ...($bucket === 'public' ? [
                $this->url('s3_public_url', 'Public URL', help: 'The address that serves the public bucket\'s files, for example https://pub-1234.r2.dev or your CDN.')
                    ->required($used)
                    ->visible($used),
            ] : []),
            $this->url("s3_{$bucket}_endpoint", 'Endpoint', help: 'The address of your storage service, for example https://s3.fr-par.scw.cloud. Leave it empty for AWS S3.')
                ->visible($used),
            $this->text("s3_{$bucket}_region", 'Region', help: 'Where the bucket is kept, for example eu-west-1 or fr-par. Use auto for Cloudflare R2.')
                ->required($used)
                ->visible($used),
            $this->text("s3_{$bucket}_key", 'Access key ID')
                ->required($used)
                ->visible($used),
            $this->secret("s3_{$bucket}_secret", 'Secret access key')
                ->required(fn (Get $get): bool => $used($get) && ! $this->isSaved("s3_{$bucket}_secret"))
                ->visible($used),
            $this->toggle("s3_{$bucket}_path_style", 'Use path-style addresses',
                help: 'Turn this on if your storage server needs it, as MinIO does. Most services, like AWS S3, work with it off.')
                ->visible($used)
                ->columnSpanFull(),
        ];
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
    private static function bucketProperties(): array
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
        return $this->usesS3($get) && match ($bucket) {
            'public' => $get('public_files') === 'bucket',
            'backup' => $get('backup_files') === 'bucket',
            default => true,
        };
    }

    /**
     * The two "keep … on" choices are no settings: picking this server for
     * public images, or the private bucket for backups, forgets the bucket
     * named for them and its connection.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    #[\Override]
    protected function valuesToSave(array $data): array
    {
        $values = parent::valuesToSave($data);

        foreach (['public' => 'public_files', 'backup' => 'backup_files'] as $bucket => $choice) {
            if (array_key_exists($choice, $data) && $data[$choice] !== 'bucket') {
                foreach (self::bucketProperties() as $property) {
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
