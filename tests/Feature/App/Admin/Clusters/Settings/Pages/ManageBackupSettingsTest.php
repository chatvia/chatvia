<?php

use App\Admin\Backup\Actions\RunBackupAction;
use App\Admin\Backup\Enums\BackupStatus;
use App\Admin\Backup\Enums\BackupTrigger;
use App\Admin\Backup\Jobs\RunBackupJob;
use App\Admin\Backup\Models\Backup;
use App\Admin\Clusters\Settings\Pages\ManageBackupSettings;
use App\Admin\Settings\BackupSettings;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/**
 * Settings → Backups: a public key is checked with gpg before it is saved,
 * "Back up now" queues a backup made by hand that the worker hands to the
 * backup action, and deleting a backup removes its file too.
 */
beforeEach(function (): void {
    $this->admin = adminUser();
    Filament::setCurrentPanel('admin');
    Storage::fake('backups');
});

it('refuses a public key gpg cannot encrypt to, and saves one it can', function (): void {
    if (! programInstalled('gpg')) {
        $this->markTestSkipped('gpg is not installed.');
    }

    adminPage(ManageBackupSettings::class, $this->admin)
        ->fillForm(['public_key' => File::get(base_path('tests/Fixtures/backup/signonly.asc'))])
        ->call('save')
        ->assertHasFormErrors(['public_key']);

    expect(savedSetting(BackupSettings::class, 'public_key'))->toBeNull();

    adminPage(ManageBackupSettings::class, $this->admin)
        ->fillForm(['public_key' => File::get(base_path('tests/Fixtures/backup/public.asc'))])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(savedSetting(BackupSettings::class, 'public_key'))->not->toBeNull();
});

it('queues a backup made by hand, which the worker runs through the backup action', function (): void {
    Queue::fake();

    adminPage(ManageBackupSettings::class, $this->admin)
        ->callAction('backUpNow');

    $backup = Backup::query()->sole();

    expect($backup->trigger)->toBe(BackupTrigger::Manual)
        ->and($backup->status)->toBe(BackupStatus::Pending);
    Queue::assertPushed(RunBackupJob::class, fn (RunBackupJob $job): bool => $job->backup->is($backup));

    $this->mock(RunBackupAction::class)
        ->shouldReceive('execute')->once()
        ->withArgs(fn (Backup $given): bool => $given->is($backup))
        ->andReturnArg(0);

    app()->call([Queue::pushed(RunBackupJob::class)->sole(), 'handle']);
});

it('deletes a backup with its file', function (): void {
    $backup = Backup::factory()->completed()->create();
    Storage::disk('backups')->put((string) $backup->path, 'archive');

    adminPage(ManageBackupSettings::class, $this->admin)
        ->callAction(TestAction::make('delete')->table($backup));

    expect(Backup::query()->exists())->toBeFalse()
        ->and(Storage::disk('backups')->exists((string) $backup->path))->toBeFalse();
});
