<?php

use App\Admin\Backup\Actions\RunBackupAction;
use App\Admin\Backup\Enums\BackupStatus;
use App\Admin\Backup\Enums\BackupTrigger;
use App\Admin\Backup\Models\Backup;

/**
 * chatvia:backup:create: a backup now (made by hand), or with --if-due only
 * when the automatic backup is on and due, as the scheduler runs it. A
 * failed backup fails the command with its reason.
 */
beforeEach(function (): void {
    config(['chatvia.backup.enabled' => true, 'chatvia.backup.frequency' => '24h']);
});

function cbFakeRun(bool $succeeds = true): void
{
    test()->mock(RunBackupAction::class)
        ->shouldReceive('execute')
        ->andReturnUsing(fn (Backup $backup): Backup => tap($backup)->update($succeeds
            ? ['status' => BackupStatus::Completed, 'disk' => 'private', 'path' => 'backups/chatvia-2026-10-26-010000.tar', 'size' => 2048]
            : ['status' => BackupStatus::Failed, 'error' => 'pg_dump failed: connection refused']));
}

it('backs up now as a backup made by hand', function (): void {
    cbFakeRun();

    $this->artisan('chatvia:backup:create')
        ->expectsOutputToContain('Backed up to private:backups/chatvia-2026-10-26-010000.tar (2.0 KB)')
        ->assertSuccessful();

    expect(Backup::query()->sole()->trigger)->toBe(BackupTrigger::Manual);
});

it('makes a scheduled backup when one is due', function (): void {
    cbFakeRun();

    $this->artisan('chatvia:backup:create --if-due')->assertSuccessful();

    expect(Backup::query()->sole()->trigger)->toBe(BackupTrigger::Scheduled);
});

it('does nothing with --if-due when no backup is due or backups are off', function (bool $enabled): void {
    config(['chatvia.backup.enabled' => $enabled]);
    Backup::factory()->completed(now()->subHour())->create();
    $this->mock(RunBackupAction::class)->shouldNotReceive('execute');

    $this->artisan('chatvia:backup:create --if-due')->assertSuccessful();

    expect(Backup::query()->count())->toBe(1);
})->with(['on, not due' => [true], 'off' => [false]]);

it('fails with the reason when the backup fails', function (): void {
    cbFakeRun(succeeds: false);

    $this->artisan('chatvia:backup:create')
        ->expectsOutputToContain('The backup failed: pg_dump failed: connection refused')
        ->assertFailed();
});
