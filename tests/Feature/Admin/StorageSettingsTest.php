<?php

use App\Admin\Clusters\Settings\Pages\ManageStorageSettings;
use App\Admin\Settings\StorageDisks;
use App\Admin\Settings\StorageSettings;
use App\Identity\Workspace\Models\User;
use Domain\Conversation\Models\Attachment;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * Settings → Storage: the private disk stays on this server until
 * S3-compatible storage is chosen there, then it uses that service (the `s3`
 * disk of config/filesystems.php), also in a process that had it open
 * already. A button checks the saved storage.
 */
beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create()->fresh();
    Filament::setCurrentPanel('admin');
});

function storagePage(User $admin): Testable
{
    return Livewire::actingAs($admin, 'admin')->test(ManageStorageSettings::class);
}

it('keeps files on this server by default', function (): void {
    expect(config('filesystems.storage'))->toBe('local')
        ->and(config('filesystems.disks.private.driver'))->toBe('local')
        ->and(config('filesystems.disks.private.root'))->toBe(storage_path('app/private'));
});

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

it('moves the private disk to S3-compatible storage once it is saved, also for a process that opened it', function (): void {
    $before = Storage::disk('private');

    storagePage($this->admin)
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

it('keeps knowledge files and chat attachments on the private disk, so they follow the storage setting', function (): void {
    expect(config('filesystems.default'))->toBe('private')
        ->and(Attachment::DISK)->toBe('private');
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

it('refuses S3-compatible storage without its bucket and keys', function (): void {
    storagePage($this->admin)
        ->fillForm(['driver' => 's3'])
        ->call('save')
        ->assertHasFormErrors(['s3_region', 's3_key', 's3_secret', 's3_bucket']);

    expect(config('filesystems.disks.private.driver'))->toBe('local');
});

it('checks the saved storage by writing, reading and deleting a file', function (): void {
    Storage::fake('private');

    storagePage($this->admin)
        ->callAction('testStorage')
        ->assertNotified('Storage works');

    expect(Storage::disk('private')->allFiles())->toBe([]);
});

it('says why the storage failed', function (): void {
    $failing = Mockery::mock(Filesystem::class);
    $failing->shouldReceive('put')->andThrow(new RuntimeException('Access Denied'));
    Storage::set('private', $failing);

    storagePage($this->admin)
        ->callAction('testStorage')
        ->assertNotified(Notification::make()->danger()->title('Storage does not work')->body('The private storage failed: Access Denied')->persistent());
});
