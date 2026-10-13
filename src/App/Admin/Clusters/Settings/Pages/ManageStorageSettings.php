<?php

declare(strict_types=1);

namespace App\Admin\Clusters\Settings\Pages;

use App\Admin\Clusters\Settings\AdminSettingsPage;
use App\Admin\Clusters\Settings\SettingsCard;
use App\Admin\Settings\StorageDisks;
use App\Admin\Settings\StorageSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Where the app keeps its files: on this server, or in S3-compatible object
 * storage (AWS S3, Scaleway, Cloudflare R2, MinIO, ...), with a button that
 * checks the saved storage by writing, reading and deleting a small file.
 * Switching moves no files, so the page says so.
 */
class ManageStorageSettings extends AdminSettingsPage
{
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
        return 'Brand and platform';
    }

    public function overviewStatus(): array
    {
        if ($this->valueInUse('driver') !== 's3') {
            return ['On this server', 'gray'];
        }

        return ['S3-compatible storage', filled($this->valueInUse('private_bucket')) && filled($this->valueInUse('public_bucket')) ? 'success' : 'warning'];
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
                ->modalDescription('Writes a small file to the private and the public storage with the saved settings, reads it back and deletes it. Save your changes first.')
                ->modalSubmitActionLabel('Test')
                ->action(function (): void {
                    $failures = array_filter(array_map($this->checkDisk(...), StorageDisks::DISKS));

                    if ($failures === []) {
                        Notification::make()->success()->title('Storage works')->body('Files were written, read and deleted.')->send();

                        return;
                    }

                    Notification::make()->danger()->title('Storage does not work')->body(implode("\n", $failures))->persistent()->send();
                }),
        ];
    }

    protected function cards(): array
    {
        return [
            SettingsCard::make('location', 'Where files are kept', 'Knowledge files and backups, chat attachments and chunk images.', Heroicon::OutlinedArchiveBox, fn (): array => [
                $this->choice('driver', 'Store files in', self::DRIVERS,
                    help: 'This server needs no account. S3-compatible storage keeps files off the server and scales with them.')
                    ->live(),
                Callout::make('Switching moves no files')
                    ->description('Files already stored stay where they are, and the app no longer finds them. Switch before people add files, or copy the files to the new place first.')
                    ->icon(Heroicon::OutlinedExclamationTriangle)
                    ->warning(),
            ])->summary(fn (): string => self::DRIVERS[(string) $this->valueInUse('driver')] ?? self::DRIVERS['local']),
            SettingsCard::make('s3', 'S3-compatible storage', 'AWS S3, Scaleway Object Storage, Cloudflare R2, MinIO and other services with the S3 API.', Heroicon::OutlinedCloud, fn (): array => [
                $this->url('s3_endpoint', 'Endpoint',
                    help: 'For example https://s3.fr-par.scw.cloud, https://<account-id>.r2.cloudflarestorage.com or http://minio:9000. Leave empty for AWS S3.'),
                $this->text('s3_region', 'Region', help: 'For example fr-par, eu-west-1, or auto for Cloudflare R2.')
                    ->required(fn (Get $get): bool => $this->usesS3($get)),
                $this->text('s3_key', 'Access key ID')
                    ->required(fn (Get $get): bool => $this->usesS3($get)),
                $this->secret('s3_secret', 'Secret access key')
                    ->required(fn (Get $get): bool => $this->usesS3($get) && ! $this->isSaved('s3_secret')),
                $this->toggle('s3_path_style', 'Use path-style addresses',
                    help: 'Puts the bucket in the path instead of the host name. MinIO and some other servers need it.'),
                $this->text('private_bucket', 'Private bucket', help: 'Knowledge files and backups. Keep this bucket private.')
                    ->required(fn (Get $get): bool => $this->usesS3($get)),
                $this->text('public_bucket', 'Public bucket', help: 'Chat attachments and chunk images. Anyone with a file\'s link can open it.')
                    ->required(fn (Get $get): bool => $this->usesS3($get)),
                $this->url('public_url', 'Public files address',
                    help: 'Where the public bucket\'s files are served from, for example a CDN. Leave empty to use the bucket\'s own address.'),
            ])->required('s3_region', 's3_key', 's3_secret', 'private_bucket', 'public_bucket'),
        ];
    }

    /** Whether the form stores files in S3-compatible storage. */
    private function usesS3(Get $get): bool
    {
        return ($get('driver') ?? $this->valueInUse('driver')) === 's3';
    }

    /**
     * Write, read and delete a small file on a disk with the saved settings;
     * why it failed, or null.
     */
    private function checkDisk(string $disk): ?string
    {
        $path = 'chatvia-storage-test-'.Str::random(12).'.txt';
        $content = 'Chatvia storage test '.now()->toIso8601String();

        try {
            $storage = Storage::disk($disk);
            $storage->put($path, $content);
            $read = $storage->get($path);
            $storage->delete($path);

            return $read === $content ? null : "The {$disk} storage returned other content than was written.";
        } catch (Throwable $e) {
            return "The {$disk} storage failed: ".Str::limit($e->getMessage(), 300);
        }
    }
}
