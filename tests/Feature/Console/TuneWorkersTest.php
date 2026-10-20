<?php

use App\Admin\Settings\HorizonWorkers;
use App\Admin\Settings\PlatformSettings;
use Laravel\Horizon\Contracts\HorizonCommandQueue;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;

/**
 * chatvia:workers:tune sizes the queue workers to this machine and saves them as
 * the Settings → Platform values, for installers and scripts. The sizing
 * itself is in tests/Unit/Admin/Settings/WorkerSizingTest.php.
 */
function tuneWorkersHorizonNotRunning(): void
{
    $masters = Mockery::mock(MasterSupervisorRepository::class);
    $masters->allows('names')->andReturn([]);

    app()->instance(HorizonWorkers::class, new HorizonWorkers(
        $masters,
        Mockery::mock(SupervisorRepository::class),
        Mockery::mock(HorizonCommandQueue::class),
    ));
}

it('saves the recommended worker numbers', function (): void {
    tuneWorkersHorizonNotRunning();

    $this->artisan('chatvia:workers:tune')
        ->expectsOutputToContain('Saved.')
        ->assertSuccessful();

    // The machine running this test is unknown; the floor is the defaults.
    expect(savedSetting(PlatformSettings::class, 'chat_workers'))->toBeGreaterThanOrEqual(6)
        ->and(savedSetting(PlatformSettings::class, 'media_workers'))->toBeGreaterThanOrEqual(1);
});

it('saves nothing with --dry-run', function (): void {
    tuneWorkersHorizonNotRunning();

    $this->artisan('chatvia:workers:tune', ['--dry-run' => true])
        ->expectsOutputToContain('Nothing was saved (--dry-run).')
        ->assertSuccessful();

    expect(savedSetting(PlatformSettings::class, 'chat_workers'))->toBeNull();
});
