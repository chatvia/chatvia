<?php

use App\Admin\Backup\Enums\BackupStatus;
use App\Admin\Backup\Models\Backup;
use App\Admin\Backup\Services\BackupSchedule;

/**
 * When the automatic backup is due: off means never, no backup yet means
 * now, then once the newest scheduled backup is as old as the frequency
 * (with a few minutes of slack for the hourly check), sooner after a failure,
 * and backups made by hand do not count. An unknown frequency means daily.
 */
beforeEach(function (): void {
    config(['chatvia.backup.enabled' => true, 'chatvia.backup.frequency' => '24h']);
});

it('is due only when the schedule says so', function (Closure $arrange, bool $due): void {
    $arrange();

    expect(app(BackupSchedule::class)->isDue())->toBe($due);
})->with([
    'automatic backups off' => [fn () => config(['chatvia.backup.enabled' => false]), false],
    'no backup yet' => [fn () => null, true],
    'newest scheduled backup younger than the frequency' => [fn () => Backup::factory()->create(['status' => BackupStatus::Completed, 'created_at' => now()->subHours(23)]), false],
    'a few minutes short of the frequency' => [fn () => Backup::factory()->create(['status' => BackupStatus::Completed, 'created_at' => now()->sub('23 hours 58 minutes')]), true],
    'weekly, but the last one failed 6 hours ago' => [function (): void {
        config(['chatvia.backup.frequency' => 'week']);
        Backup::factory()->create(['status' => BackupStatus::Failed, 'created_at' => now()->subHours(6)]);
    }, true],
    'only a backup made by hand' => [fn () => Backup::factory()->manual()->completed()->create(), true],
]);

it('falls back to every 24 hours for an unknown frequency', function (): void {
    config(['chatvia.backup.frequency' => 'hourly']);

    expect(app(BackupSchedule::class)->frequency()->hours())->toBe(24);
});
