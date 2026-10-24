<?php

use App\Admin\Settings\StorageDisks;
use Domain\Conversation\Models\Attachment;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Local\LocalFilesystemAdapter;

/**
 * StorageDisks: while S3-compatible storage is chosen (Settings → Storage),
 * the private disk, and with it the knowledge files and chat attachments,
 * is the `s3` disk of config/filesystems.php; once it is not, it is this
 * server's disk again, also in a process that started on S3.
 */
it('gives the private disk the s3 disk once S3-compatible storage is chosen', function (): void {
    config([
        'filesystems.storage' => 's3',
        'filesystems.disks.s3.endpoint' => 'https://s3.fr-par.scw.cloud',
        'filesystems.disks.s3.region' => 'fr-par',
        'filesystems.disks.s3.bucket' => 'files',
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
        'filesystems.disks.s3.region' => 'fr-par',
        'filesystems.disks.s3.bucket' => 'files',
    ]);
    app(StorageDisks::class)->apply();

    // Knowledge files go to the default disk, attachments to their own.
    expect(Storage::disk()->getAdapter())->toBeInstanceOf(AwsS3V3Adapter::class)
        ->and(Storage::disk(Attachment::DISK)->getAdapter())->toBeInstanceOf(AwsS3V3Adapter::class);
});

it('goes back to this server in a process that started on S3-compatible storage', function (): void {
    $local = config('filesystems.disks.private');
    config(['filesystems.storage' => 's3', 'filesystems.disks.s3.bucket' => 'files']);

    // A worker that booted while S3 was chosen.
    $disks = new StorageDisks(app('config'), app());
    $disks->apply();
    expect(config('filesystems.disks.private.driver'))->toBe('s3');

    config(['filesystems.storage' => 'local']);
    $disks->apply();

    expect(config('filesystems.disks.private'))->toBe($local);
});
