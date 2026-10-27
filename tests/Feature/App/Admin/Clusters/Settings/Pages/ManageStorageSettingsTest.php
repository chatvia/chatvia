<?php

use App\Admin\Clusters\Settings\Pages\ManageStorageSettings;
use App\Admin\Settings\StorageSettings;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Settings → Storage: files stay on this server until S3-compatible storage
 * is chosen there; then the private disk uses that service at once, also in
 * a process that had it open already, and public images and backups use
 * their own buckets, each with its own connection, when named. A button checks the saved storage.
 * How the disks follow the setting is StorageDisksTest's.
 */
beforeEach(function (): void {
    $this->admin = adminUser();
    Filament::setCurrentPanel('admin');
});

it('shows the S3 fields only while S3-compatible storage is chosen', function (): void {
    config(['filesystems.storage' => 'local']);

    adminPage(ManageStorageSettings::class, $this->admin)
        ->assertFormFieldHidden('s3_private_region')
        ->fillForm(['driver' => 's3'])
        ->assertFormFieldVisible('s3_private_region')
        ->assertFormFieldVisible('s3_private_bucket');
});

it('moves the private disk to S3-compatible storage once it is saved, also for a process that opened it', function (): void {
    $before = Storage::disk('private');

    adminPage(ManageStorageSettings::class, $this->admin)
        ->fillForm([
            'driver' => 's3',
            's3_private_endpoint' => 'http://minio:9000',
            's3_private_region' => 'us-east-1',
            's3_private_key' => 'minio-user',
            's3_private_secret' => 'minio-secret',
            's3_private_path_style' => true,
            's3_private_bucket' => 'chatvia',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(config('filesystems.disks.private'))->toMatchArray([
        'driver' => 's3',
        'endpoint' => 'http://minio:9000',
        'key' => 'minio-user',
        'secret' => 'minio-secret',
        'use_path_style_endpoint' => true,
        'bucket' => 'chatvia',
    ])
        ->and(Storage::disk('private'))->not->toBe($before);

    app()->forgetScopedInstances();

    expect(app(StorageSettings::class)->s3_private_secret)->toBe('minio-secret');
});

it('refuses S3-compatible storage without its bucket and keys', function (): void {
    adminPage(ManageStorageSettings::class, $this->admin)
        ->fillForm(['driver' => 's3'])
        ->call('save')
        ->assertHasFormErrors(['s3_private_region', 's3_private_key', 's3_private_secret', 's3_private_bucket']);

    expect(config('filesystems.disks.private.driver'))->toBe('local');
});

it('checks the saved storage by writing, reading and deleting a file on each disk', function (): void {
    Storage::fake('private');
    Storage::fake('assets');
    Storage::fake('backups');

    adminPage(ManageStorageSettings::class, $this->admin)
        ->callAction('testStorage')
        ->assertNotified('Storage works');

    expect(Storage::disk('private')->allFiles())->toBe([])
        ->and(Storage::disk('assets')->allFiles())->toBe([])
        ->and(Storage::disk('backups')->allFiles())->toBe([]);
});

it('says why the storage failed', function (): void {
    $failing = Mockery::mock(Filesystem::class);
    $failing->shouldReceive('put')->andThrow(new RuntimeException('Access Denied'));
    Storage::set('private', $failing);

    adminPage(ManageStorageSettings::class, $this->admin)
        ->callAction('testStorage')
        ->assertNotified(Notification::make()->danger()->title('Storage does not work')->body('The private storage failed: Access Denied')->persistent());
});

it('keeps public images on this server and backups in the private bucket until a bucket of their own is picked', function (): void {
    adminPage(ManageStorageSettings::class, $this->admin)
        ->fillForm(['driver' => 's3'])
        ->assertFormSet(['public_files' => 'local', 'backup_files' => 'private'])
        ->assertFormFieldHidden('s3_public_bucket')
        ->assertFormFieldHidden('s3_public_key')
        ->assertFormFieldHidden('s3_backup_bucket')
        ->assertFormFieldHidden('s3_backup_key')
        ->fillForm(['public_files' => 'bucket', 'backup_files' => 'bucket'])
        ->assertFormFieldVisible('s3_public_bucket')
        ->assertFormFieldVisible('s3_public_url')
        ->assertFormFieldVisible('s3_public_key')
        ->assertFormFieldVisible('s3_backup_bucket')
        ->assertFormFieldVisible('s3_backup_key');
});

it('saves the public and backups buckets with their own connections, and forgets them when this server or the private bucket is picked again', function (): void {
    $s3 = ['driver' => 's3', 's3_private_region' => 'fr-par', 's3_private_key' => 'scw-key', 's3_private_secret' => 'scw-secret', 's3_private_bucket' => 'chatvia'];

    $page = adminPage(ManageStorageSettings::class, $this->admin)
        ->fillForm([
            ...$s3,
            'public_files' => 'bucket',
            's3_public_bucket' => 'chatvia-public',
            's3_public_url' => 'https://pub-1234.r2.dev',
            's3_public_region' => 'auto',
            's3_public_key' => 'r2-key',
            's3_public_secret' => 'r2-secret',
            'backup_files' => 'bucket',
            's3_backup_bucket' => 'chatvia-backups',
            's3_backup_region' => 'eu-west-1',
            's3_backup_key' => 'aws-key',
            's3_backup_secret' => 'aws-secret',
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertFormSet(['public_files' => 'bucket', 'backup_files' => 'bucket']);

    expect(config('filesystems.disks.private'))->toMatchArray(['bucket' => 'chatvia', 'key' => 'scw-key'])
        ->and(config('filesystems.disks.assets'))->toMatchArray(['driver' => 's3', 'bucket' => 'chatvia-public', 'url' => 'https://pub-1234.r2.dev', 'key' => 'r2-key', 'secret' => 'r2-secret'])
        ->and(config('filesystems.disks.backups'))->toMatchArray(['driver' => 's3', 'bucket' => 'chatvia-backups', 'key' => 'aws-key', 'region' => 'eu-west-1']);

    $page->fillForm(['public_files' => 'local', 'backup_files' => 'private'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(config('filesystems.disks.assets.driver'))->toBe('local')
        ->and(config('filesystems.disks.backups'))->toMatchArray(['bucket' => 'chatvia', 'key' => 'scw-key'])
        ->and(savedSetting(StorageSettings::class, 's3_public_bucket'))->toBeNull()
        ->and(savedSetting(StorageSettings::class, 's3_public_secret'))->toBeNull()
        ->and(savedSetting(StorageSettings::class, 's3_backup_bucket'))->toBeNull()
        ->and(savedSetting(StorageSettings::class, 's3_backup_key'))->toBeNull();
});

it('refuses a public bucket without its URL, or one that is also the private or backups bucket', function (): void {
    $s3 = [
        'driver' => 's3',
        's3_private_region' => 'auto',
        's3_private_key' => 'k',
        's3_private_secret' => 's',
        's3_private_bucket' => 'chatvia',
        'public_files' => 'bucket',
        's3_public_region' => 'auto',
        's3_public_key' => 'k',
        's3_public_secret' => 's',
    ];

    adminPage(ManageStorageSettings::class, $this->admin)
        ->fillForm([...$s3, 's3_public_bucket' => 'chatvia-public'])
        ->call('save')
        ->assertHasFormErrors(['s3_public_url' => 'required'])
        ->fillForm([...$s3, 's3_public_bucket' => 'chatvia', 's3_public_url' => 'https://pub-1234.r2.dev'])
        ->call('save')
        ->assertHasFormErrors(['s3_public_bucket' => 'different'])
        ->fillForm([...$s3, 's3_public_bucket' => 'chatvia-public', 's3_public_url' => 'https://pub-1234.r2.dev', 'backup_files' => 'bucket', 's3_backup_bucket' => 'chatvia-public', 's3_backup_region' => 'auto', 's3_backup_key' => 'k', 's3_backup_secret' => 's'])
        ->call('save')
        ->assertHasFormErrors(['s3_public_bucket' => 'different']);

    expect(config('filesystems.disks.assets.driver'))->toBe('local');
});
