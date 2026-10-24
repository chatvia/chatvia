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
 * a process that had it open already. A button checks the saved storage.
 * How the disks follow the setting is StorageDisksTest's.
 */
beforeEach(function (): void {
    $this->admin = adminUser();
    Filament::setCurrentPanel('admin');
});

it('shows the S3 fields only while S3-compatible storage is chosen', function (): void {
    config(['filesystems.storage' => 'local']);

    adminPage(ManageStorageSettings::class, $this->admin)
        ->assertFormFieldHidden('s3_region')
        ->fillForm(['driver' => 's3'])
        ->assertFormFieldVisible('s3_region')
        ->assertFormFieldVisible('s3_bucket');
});

it('moves the private disk to S3-compatible storage once it is saved, also for a process that opened it', function (): void {
    $before = Storage::disk('private');

    adminPage(ManageStorageSettings::class, $this->admin)
        ->fillForm([
            'driver' => 's3',
            's3_endpoint' => 'http://minio:9000',
            's3_region' => 'us-east-1',
            's3_key' => 'minio-user',
            's3_secret' => 'minio-secret',
            's3_path_style' => true,
            's3_bucket' => 'chatvia',
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

    expect(app(StorageSettings::class)->s3_secret)->toBe('minio-secret');
});

it('refuses S3-compatible storage without its bucket and keys', function (): void {
    adminPage(ManageStorageSettings::class, $this->admin)
        ->fillForm(['driver' => 's3'])
        ->call('save')
        ->assertHasFormErrors(['s3_region', 's3_key', 's3_secret', 's3_bucket']);

    expect(config('filesystems.disks.private.driver'))->toBe('local');
});

it('checks the saved storage by writing, reading and deleting a file', function (): void {
    Storage::fake('private');

    adminPage(ManageStorageSettings::class, $this->admin)
        ->callAction('testStorage')
        ->assertNotified('Storage works');

    expect(Storage::disk('private')->allFiles())->toBe([]);
});

it('says why the storage failed', function (): void {
    $failing = Mockery::mock(Filesystem::class);
    $failing->shouldReceive('put')->andThrow(new RuntimeException('Access Denied'));
    Storage::set('private', $failing);

    adminPage(ManageStorageSettings::class, $this->admin)
        ->callAction('testStorage')
        ->assertNotified(Notification::make()->danger()->title('Storage does not work')->body('The private storage failed: Access Denied')->persistent());
});
