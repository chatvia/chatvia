<?php

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
 * Settings → Backups: the schedule and the number to keep are saved; a
 * public key is checked with gpg before it is saved and then shown with its
 * fingerprint; "Back up now" queues a backup made by hand; the list shows
 * the backups, downloads one and deletes one with its file.
 */
beforeEach(function (): void {
    $this->admin = adminUser();
    Filament::setCurrentPanel('admin');
    Storage::fake('backups');
});

it('saves the schedule and the number of backups to keep', function (): void {
    adminPage(ManageBackupSettings::class, $this->admin)
        ->fillForm(['enabled' => true, 'frequency' => '6h', 'keep' => 30])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(savedSetting(BackupSettings::class, 'frequency'))->toBe('6h')
        ->and(savedSetting(BackupSettings::class, 'keep'))->toBe(30);
});

it('refuses a frequency it does not offer', function (): void {
    adminPage(ManageBackupSettings::class, $this->admin)
        ->fillForm(['frequency' => 'hourly'])
        ->call('save')
        ->assertHasFormErrors(['frequency']);
});

it('checks the public key with gpg and shows whose it is', function (): void {
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

    adminPage(ManageBackupSettings::class, $this->admin)
        ->assertSee('Encrypted to ChatVia Backup Test')
        ->assertSee('B267 A209 E6AE C7A0 993F A603 8E89 D0F7 F34C 5DE7');
});

it('warns while backups are not encrypted', function (): void {
    adminPage(ManageBackupSettings::class, $this->admin)
        ->assertSee('Backups are not encrypted');
});

it('queues a backup made by hand', function (): void {
    Queue::fake();

    adminPage(ManageBackupSettings::class, $this->admin)
        ->callAction('backUpNow')
        ->assertNotified('Backup started');

    $backup = Backup::query()->sole();

    expect($backup->trigger)->toBe(BackupTrigger::Manual)
        ->and($backup->status)->toBe(BackupStatus::Pending);
    Queue::assertPushed(RunBackupJob::class, fn (RunBackupJob $job): bool => $job->backup->is($backup));
});

it('lists the backups, newest first, with why one failed', function (): void {
    $done = Backup::factory()->completed(now()->subDay())->create();
    $failed = Backup::factory()->create(['status' => BackupStatus::Failed, 'error' => 'pg_dump failed: connection refused']);

    adminPage(ManageBackupSettings::class, $this->admin)
        ->assertCanSeeTableRecords([$failed, $done], inOrder: true)
        ->assertSee('pg_dump failed: connection refused');
});

it('downloads a backup', function (): void {
    $backup = Backup::factory()->completed()->create();
    Storage::disk('backups')->put((string) $backup->path, 'archive');

    adminPage(ManageBackupSettings::class, $this->admin)
        ->callAction(TestAction::make('download')->table($backup))
        ->assertFileDownloaded(basename((string) $backup->path), 'archive');
});

it('deletes a backup with its file', function (): void {
    $backup = Backup::factory()->completed()->create();
    Storage::disk('backups')->put((string) $backup->path, 'archive');

    adminPage(ManageBackupSettings::class, $this->admin)
        ->callAction(TestAction::make('delete')->table($backup))
        ->assertNotified('Backup deleted');

    expect(Backup::query()->exists())->toBeFalse()
        ->and(Storage::disk('backups')->exists((string) $backup->path))->toBeFalse();
});

it('shows on the overview when the last backup failed', function (): void {
    Backup::factory()->create(['status' => BackupStatus::Failed]);

    expect(app(ManageBackupSettings::class)->overviewStatus())->toBe(['The last backup failed', 'danger']);
});
