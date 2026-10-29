<?php

use App\Admin\Clusters\Settings\Pages\ManageStorageSettings;
use App\Admin\Settings\StorageSettings;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;

/**
 * Settings → Storage: once S3-compatible storage is saved, the private disk
 * uses it at once, also in a process that had it open already; a bucket
 * without its keys is refused. How the disks follow the setting is
 * StorageDisksTest's.
 */
beforeEach(function (): void {
    $this->admin = adminUser();
    Filament::setCurrentPanel('admin');
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
    app()->forgetScopedInstances();

    expect(config('filesystems.disks.private'))->toMatchArray([
        'driver' => 's3',
        'endpoint' => 'http://minio:9000',
        'key' => 'minio-user',
        'secret' => 'minio-secret',
        'use_path_style_endpoint' => true,
        'bucket' => 'chatvia',
    ])
        ->and(Storage::disk('private'))->not->toBe($before)
        ->and(app(StorageSettings::class)->s3_private_secret)->toBe('minio-secret');
});

it('refuses S3-compatible storage without its bucket and keys', function (): void {
    adminPage(ManageStorageSettings::class, $this->admin)
        ->fillForm(['driver' => 's3'])
        ->call('save')
        ->assertHasFormErrors(['s3_private_region', 's3_private_key', 's3_private_secret', 's3_private_bucket']);

    expect(config('filesystems.disks.private.driver'))->toBe('local');
});
