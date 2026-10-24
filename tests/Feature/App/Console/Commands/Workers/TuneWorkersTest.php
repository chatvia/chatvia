<?php

use App\Admin\Settings\PlatformSettings;
use App\Admin\Settings\RestartMasterSupervisor;
use App\Admin\Settings\ServerResources;
use App\Admin\Settings\WorkerSizing;
use Tests\Support\Admin\FakeHorizonWorkers;

/**
 * chatvia:workers:tune sizes the queue workers to this machine and saves them as
 * the Settings → Platform values, for installers and scripts, and restarts a
 * running Horizon that uses other numbers when asked to. The sizing itself
 * is in tests/Unit/App/Admin/Settings/WorkerSizingTest.php.
 */
it('saves the numbers recommended for this machine', function (): void {
    FakeHorizonWorkers::stopped();
    $recommended = app(WorkerSizing::class)->recommend(ServerResources::detect());

    $this->artisan('chatvia:workers:tune')
        ->expectsOutputToContain('Saved.')
        ->assertSuccessful();

    expect(savedSetting(PlatformSettings::class, 'chat_workers'))->toBe($recommended->chat)
        ->and(savedSetting(PlatformSettings::class, 'media_workers'))->toBe($recommended->media);
});

it('saves nothing with --dry-run', function (): void {
    FakeHorizonWorkers::stopped();

    $this->artisan('chatvia:workers:tune', ['--dry-run' => true])
        ->expectsOutputToContain('Nothing was saved (--dry-run).')
        ->assertSuccessful();

    expect(savedSetting(PlatformSettings::class, 'chat_workers'))->toBeNull();
});

it('restarts a Horizon that runs other numbers only with --restart', function (array $options, string $output, array $commands): void {
    // A Horizon running one chat worker, fewer than any machine is given.
    $horizon = FakeHorizonWorkers::bind(supervisors: ['web-1-abcd:chat' => 1]);

    $this->artisan('chatvia:workers:tune', $options)
        ->expectsOutputToContain($output)
        ->assertSuccessful();

    expect($horizon->commands)->toBe($commands);
})->with([
    'without --restart' => [[], 'Horizon runs other numbers: restart it to apply them', []],
    'with --restart' => [['--restart' => true], 'Asked 1 Horizon to restart', [['master:web-1-abcd', RestartMasterSupervisor::class]]],
]);
