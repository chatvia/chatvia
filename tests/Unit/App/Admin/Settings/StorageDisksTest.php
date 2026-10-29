<?php

use App\Admin\Settings\StorageDisks;
use Domain\Conversation\Models\Attachment;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Local\LocalFilesystemAdapter;

/**
 * StorageDisks: while S3-compatible storage is chosen (Settings → Storage),
 * the private disk, and with it the knowledge files and chat attachments,
 * is the `s3` disk with the private bucket's connection; once it is not, it
 * is this server's disk again, also in a process that started on S3. Public
 * images go to the public bucket and backups to the backups bucket while one
 * is named, each with its own connection, whatever the private files use.
 */
it('gives the private disk, the public images and the backups their own S3 buckets, each with its own connection', function (): void {
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

    expect(config('filesystems.disks.private'))->toMatchArray(['driver' => 's3', 'bucket' => 'files', 'region' => 'fr-par', 'key' => 'scw-key', 'use_path_style_endpoint' => false])
        ->and(config('filesystems.disks.private'))->not->toHaveKey('root')
        ->and(Storage::disk()->getAdapter())->toBeInstanceOf(AwsS3V3Adapter::class)
        ->and(Storage::disk(Attachment::DISK)->getAdapter())->toBeInstanceOf(AwsS3V3Adapter::class)
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

it('moves each disk with its own setting in a process that started on S3-compatible storage', function (): void {
    $local = config('filesystems.disks.private');
    $localAssets = config('filesystems.disks.assets');
    $localBackups = config('filesystems.disks.backups');
    config(['filesystems.storage' => 's3', 'filesystems.buckets.private.name' => 'files']);
    $disks = new StorageDisks(app('config'), app());

    $disks->apply();

    expect(config('filesystems.disks.private.driver'))->toBe('s3')
        ->and(config('filesystems.disks.assets'))->toBe($localAssets)
        ->and(config('filesystems.disks.backups'))->toBe($localBackups);

    config(['filesystems.storage' => 'local', 'filesystems.buckets.public.name' => 'images', 'filesystems.buckets.backup.name' => 'archives']);
    $disks->apply();

    expect(config('filesystems.disks.private'))->toBe($local)
        ->and(Storage::disk()->getAdapter())->toBeInstanceOf(LocalFilesystemAdapter::class)
        ->and(config('filesystems.disks.assets'))->toMatchArray(['driver' => 's3', 'bucket' => 'images'])
        ->and(config('filesystems.disks.backups'))->toMatchArray(['driver' => 's3', 'bucket' => 'archives']);

    config(['filesystems.buckets.public.name' => null, 'filesystems.buckets.backup.name' => null]);
    $disks->apply();

    expect(config('filesystems.disks.assets'))->toBe($localAssets)
        ->and(config('filesystems.disks.backups'))->toBe($localBackups);
});
