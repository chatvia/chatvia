<?php

use App\Admin\Settings\PlatformSettings;
use App\Admin\Settings\RestartMasterSupervisor;
use App\Admin\Settings\ServerResources;
use App\Admin\Settings\WorkerSizing;
use Tests\Support\Admin\FakeHorizonWorkers;

/**
 * chatvia:workers:tune sizes the queue workers to this machine and saves them
 * as the Settings → Platform values, for installers and scripts, and restarts
 * a running Horizon that uses other numbers only when asked to. The sizing
 * itself is in tests/Unit/App/Admin/Settings/WorkerSizingTest.php.
 */
it('saves the numbers recommended for this machine', function (): void {
    FakeHorizonWorkers::stopped();
    $recommended = app(WorkerSizing::class)->recommend(ServerResources::detect());

    $this->artisan('chatvia:workers:tune')->assertSuccessful();

    expect(savedSetting(PlatformSettings::class, 'chat_workers'))->toBe($recommended->chat)
        ->and(savedSetting(PlatformSettings::class, 'media_workers'))->toBe($recommended->media)
        ->and(savedSetting(PlatformSettings::class, 'generation_workers'))->toBe($recommended->generation);
});

it('restarts a Horizon that runs other numbers only with --restart', function (array $options, array $commands): void {
    $horizon = FakeHorizonWorkers::bind(supervisors: ['web-1-abcd:chat' => 1]);

    $this->artisan('chatvia:workers:tune', $options)->assertSuccessful();

    expect($horizon->commands)->toBe($commands);
})->with([
    'without --restart' => [[], []],
    'with --restart' => [['--restart' => true], [['master:web-1-abcd', RestartMasterSupervisor::class]]],
]);
