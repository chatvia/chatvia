<?php

use App\Admin\Settings\StorageDisks;
use Domain\Conversation\Models\Attachment;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Local\LocalFilesystemAdapter;

/**
 * StorageDisks: while S3-compatible storage is chosen (Settings → Storage),
 * the private disk, and with it the knowledge files and chat attachments,
 * is the `s3` disk with the private bucket's connection, public images go to
 * the public bucket when one is named and backups to the backups bucket
 * (else the private one), each with its own connection; once it is not, all
 * are this server's disks again, also in a process that started on S3.
 */
it('gives the private disk the s3 disk once S3-compatible storage is chosen', function (): void {
    config([
        'filesystems.storage' => 's3',
        'filesystems.buckets.private.endpoint' => 'https://s3.fr-par.scw.cloud',
        'filesystems.buckets.private.region' => 'fr-par',
        'filesystems.buckets.private.name' => 'files',
    ]);

    app(StorageDisks::class)->apply();

    expect(config('filesystems.disks.private'))->toMatchArray(['driver' => 's3', 'bucket' => 'files', 'region' => 'fr-par'])
        ->and(config('filesystems.disks.private'))->not->toHaveKey('root');
});

it('moves knowledge files and chat attachments with the storage setting', function (): void {
    expect(Storage::disk()->getAdapter())->toBeInstanceOf(LocalFilesystemAdapter::class)
        ->and(Storage::disk(Attachment::DISK)->getAdapter())->toBeInstanceOf(LocalFilesystemAdapter::class);

    config([
        'filesystems.storage' => 's3',
        'filesystems.buckets.private.region' => 'fr-par',
        'filesystems.buckets.private.name' => 'files',
    ]);
    app(StorageDisks::class)->apply();

    // Knowledge files go to the default disk, attachments to their own.
    expect(Storage::disk()->getAdapter())->toBeInstanceOf(AwsS3V3Adapter::class)
        ->and(Storage::disk(Attachment::DISK)->getAdapter())->toBeInstanceOf(AwsS3V3Adapter::class);
});

it('goes back to this server in a process that started on S3-compatible storage', function (): void {
    $local = config('filesystems.disks.private');
    config(['filesystems.storage' => 's3', 'filesystems.buckets.private.name' => 'files']);

    // A worker that booted while S3 was chosen.
    $disks = new StorageDisks(app('config'), app());
    $disks->apply();
    expect(config('filesystems.disks.private.driver'))->toBe('s3');

    config(['filesystems.storage' => 'local']);
    $disks->apply();

    expect(config('filesystems.disks.private'))->toBe($local);
});

it('keeps public images and backups on this server while files stay here', function (): void {
    config(['filesystems.buckets.public.name' => 'images', 'filesystems.buckets.backup.name' => 'archives']);

    app(StorageDisks::class)->apply();

    expect(config('filesystems.disks.assets.driver'))->toBe('local')
        ->and(config('filesystems.disks.backups.driver'))->toBe('local');
});

it('keeps public images on this server and backups in the private bucket when only that bucket is named', function (): void {
    $assets = config('filesystems.disks.assets');
    config(['filesystems.storage' => 's3', 'filesystems.buckets.private.region' => 'fr-par', 'filesystems.buckets.private.name' => 'files']);

    app(StorageDisks::class)->apply();

    expect(config('filesystems.disks.assets'))->toBe($assets)
        ->and(config('filesystems.disks.backups'))->toMatchArray(['driver' => 's3', 'bucket' => 'files', 'region' => 'fr-par']);
});

it('gives public images and backups their own buckets, each with its own connection', function (): void {
    config([
        'filesystems.storage' => 's3',
        'filesystems.buckets.private.endpoint' => 'https://s3.fr-par.scw.cloud',
        'filesystems.buckets.private.region' => 'fr-par',
        'filesystems.buckets.private.key' => 'scw-key',
        'filesystems.buckets.private.name' => 'files',
        'filesystems.buckets.public.name' => 'images',
        'filesystems.buckets.public.url' => 'https://images.example.org',
        'filesystems.buckets.public.endpoint' => 'https://account.r2.cloudflarestorage.com',
        'filesystems.buckets.public.region' => 'auto',
        'filesystems.buckets.public.key' => 'r2-key',
        'filesystems.buckets.backup.name' => 'archives',
        'filesystems.buckets.backup.region' => 'eu-west-1',
        'filesystems.buckets.backup.key' => 'aws-key',
        'filesystems.buckets.backup.path_style' => true,
    ]);

    app(StorageDisks::class)->apply();

    expect(config('filesystems.disks.private'))->toMatchArray(['bucket' => 'files', 'key' => 'scw-key', 'use_path_style_endpoint' => false])
        ->and(config('filesystems.disks.assets'))->toMatchArray([
            'driver' => 's3',
            'bucket' => 'images',
            'url' => 'https://images.example.org',
            'endpoint' => 'https://account.r2.cloudflarestorage.com',
            'region' => 'auto',
            'key' => 'r2-key',
        ])
        ->and(config('filesystems.disks.assets'))->not->toHaveKey('visibility')
        ->and(config('filesystems.disks.backups'))->toMatchArray(['driver' => 's3', 'bucket' => 'archives', 'region' => 'eu-west-1', 'key' => 'aws-key', 'endpoint' => null, 'use_path_style_endpoint' => true])
        ->and(Storage::disk('assets')->url('workspaces/a/logo.png'))->toBe('https://images.example.org/workspaces/a/logo.png');
});

it('puts every disk back on this server in a process that started on S3-compatible storage', function (): void {
    $local = ['assets' => config('filesystems.disks.assets'), 'backups' => config('filesystems.disks.backups')];
    config(['filesystems.storage' => 's3', 'filesystems.buckets.private.name' => 'files', 'filesystems.buckets.public.name' => 'images', 'filesystems.buckets.backup.name' => 'archives']);

    $disks = new StorageDisks(app('config'), app());
    $disks->apply();
    config(['filesystems.storage' => 'local']);
    $disks->apply();

    expect(config('filesystems.disks.assets'))->toBe($local['assets'])
        ->and(config('filesystems.disks.backups'))->toBe($local['backups']);
});
