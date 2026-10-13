<?php

use App\Admin\Clusters\Settings\Pages\ManageStorageSettings;
use App\Admin\Settings\StorageDisks;
use App\Admin\Settings\StorageSettings;
use App\Identity\Workspace\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * Settings → Storage: the private and public disks stay on this server until
 * S3-compatible storage is chosen there (or in .env), then both disks use
 * that service, also in a process that had them open already. A button
 * checks the saved storage.
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
    expect(config('filesystems.disks.private.driver'))->toBe('local')
        ->and(config('filesystems.disks.private.root'))->toBe(storage_path('app/private'))
        ->and(config('filesystems.disks.public.driver'))->toBe('local');
});

it('builds S3 disks from the .env values while nothing is saved', function (): void {
    config([
        'chatvia.storage.driver' => 's3',
        'chatvia.storage.s3.endpoint' => 'https://s3.fr-par.scw.cloud',
        'chatvia.storage.s3.region' => 'fr-par',
        'chatvia.storage.s3.private_bucket' => 'files',
        'chatvia.storage.s3.public_bucket' => 'files-public',
    ]);

    app(StorageDisks::class)->apply();

    expect(config('filesystems.disks.private'))->toMatchArray(['driver' => 's3', 'bucket' => 'files', 'region' => 'fr-par'])
        ->and(config('filesystems.disks.private'))->not->toHaveKey('root')
        ->and(config('filesystems.disks.public'))->toMatchArray(['driver' => 's3', 'bucket' => 'files-public', 'visibility' => 'public']);
});

it('moves both disks to S3-compatible storage once it is saved, also for a process that opened them', function (): void {
    $before = Storage::disk('private');

    storagePage($this->admin)
        ->fillForm([
            'driver' => 's3',
            's3_endpoint' => 'http://minio:9000',
            's3_region' => 'us-east-1',
            's3_key' => 'minio-user',
            's3_secret' => 'minio-secret',
            's3_path_style' => true,
            'private_bucket' => 'chatvia',
            'public_bucket' => 'chatvia-public',
            'public_url' => 'https://files.example.com',
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
        ->and(config('filesystems.disks.public'))->toMatchArray(['bucket' => 'chatvia-public', 'url' => 'https://files.example.com'])
        ->and(Storage::disk('private'))->not->toBe($before);

    app()->forgetScopedInstances();

    expect(app(StorageSettings::class)->s3_secret)->toBe('minio-secret');
});

it('keeps knowledge files on the private disk, so they follow the storage setting', function (): void {
    expect(config('filesystems.default'))->toBe('private');
});

it('goes back to this server in a process that started on S3-compatible storage', function (): void {
    $local = config('filesystems.disks.private');
    config(['chatvia.storage.driver' => 's3', 'chatvia.storage.s3.private_bucket' => 'files', 'chatvia.storage.s3.public_bucket' => 'files-public']);

    // A worker that booted while S3 was chosen.
    $disks = new StorageDisks(app('config'), app());
    $disks->apply();
    expect(config('filesystems.disks.private.driver'))->toBe('s3');

    config(['chatvia.storage.driver' => 'local']);
    $disks->apply();

    expect(config('filesystems.disks.private'))->toBe($local);
});

it('refuses S3-compatible storage without its buckets and keys', function (): void {
    storagePage($this->admin)
        ->fillForm(['driver' => 's3'])
        ->call('save')
        ->assertHasFormErrors(['s3_region', 's3_key', 's3_secret', 'private_bucket', 'public_bucket']);

    expect(config('filesystems.disks.private.driver'))->toBe('local');
});

it('checks the saved storage by writing, reading and deleting a file', function (): void {
    Storage::fake('private');
    Storage::fake('public');

    storagePage($this->admin)
        ->callAction('testStorage')
        ->assertNotified('Storage works');

    expect(Storage::disk('private')->allFiles())->toBe([])
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('says which storage failed and why', function (): void {
    Storage::fake('public');
    $failing = Mockery::mock(Filesystem::class);
    $failing->shouldReceive('put')->andThrow(new RuntimeException('Access Denied'));
    Storage::set('private', $failing);

    storagePage($this->admin)
        ->callAction('testStorage')
        ->assertNotified(Notification::make()->danger()->title('Storage does not work')->body('The private storage failed: Access Denied')->persistent());
});
