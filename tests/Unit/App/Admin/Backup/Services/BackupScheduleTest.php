<?php

use App\Admin\Backup\Enums\BackupStatus;
use App\Admin\Backup\Models\Backup;
use App\Admin\Backup\Services\BackupSchedule;

/**
 * When the automatic backup is due: off means never, no backup yet means
 * now, then once the newest scheduled backup is as old as the frequency
 * (with a few minutes of slack for the hourly check), sooner after a failure,
 * and backups made by hand do not count.
 */
beforeEach(function (): void {
    config(['chatvia.backup.enabled' => true, 'chatvia.backup.frequency' => '24h']);
});

function bsScheduled(string $age, BackupStatus $status = BackupStatus::Completed): Backup
{
    return Backup::factory()->create(['status' => $status, 'created_at' => now()->sub($age)]);
}

it('is never due while automatic backups are off', function (): void {
    config(['chatvia.backup.enabled' => false]);

    expect(app(BackupSchedule::class)->nextDueAt())->toBeNull()
        ->and(app(BackupSchedule::class)->isDue())->toBeFalse();
});

it('is due at once before the first backup', function (): void {
    expect(app(BackupSchedule::class)->isDue())->toBeTrue();
});

it('is due once the newest scheduled backup is as old as the frequency', function (string $frequency, string $age, bool $due): void {
    config(['chatvia.backup.frequency' => $frequency]);
    bsScheduled($age);

    expect(app(BackupSchedule::class)->isDue())->toBe($due);
})->with([
    '6h, 5 hours ago' => ['6h', '5 hours', false],
    '6h, 6 hours ago' => ['6h', '6 hours', true],
    '24h, 23 hours ago' => ['24h', '23 hours', false],
    '24h, a few seconds short of 24 hours' => ['24h', '23 hours 58 minutes', true],
    '48h, 47 hours ago' => ['48h', '47 hours', false],
    '48h, 48 hours ago' => ['48h', '48 hours', true],
    'week, 6 days ago' => ['week', '6 days', false],
    'week, 7 days ago' => ['week', '7 days', true],
]);

it('tries again after at most 6 hours when the last backup failed', function (): void {
    config(['chatvia.backup.frequency' => 'week']);
    bsScheduled('6 hours', BackupStatus::Failed);

    expect(app(BackupSchedule::class)->isDue())->toBeTrue();
});

it('does not count backups made by hand', function (): void {
    Backup::factory()->manual()->completed()->create();

    expect(app(BackupSchedule::class)->isDue())->toBeTrue();
});

it('falls back to every 24 hours for an unknown frequency', function (): void {
    config(['chatvia.backup.frequency' => 'hourly']);

    expect(app(BackupSchedule::class)->frequency()->hours())->toBe(24);
});
