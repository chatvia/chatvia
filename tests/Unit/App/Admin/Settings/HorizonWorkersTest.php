<?php

use App\Admin\Settings\RestartMasterSupervisor;
use Tests\Support\Admin\FakeHorizonWorkers;

/**
 * The queue workers Horizon runs now: the panel compares their numbers with
 * the saved ones, and restarts Horizon through its command queue in Redis,
 * which also reaches a Horizon in another container or on another server.
 */
it('finds a running supervisor that uses another number of workers', function (): void {
    $workers = new FakeHorizonWorkers(supervisors: ['web-1-abcd:chat' => 6, 'web-1-abcd:voice' => 3])->workers();

    expect($workers->differsFrom(['chat' => 6, 'voice' => 3]))->toBeFalse()
        ->and($workers->differsFrom(['chat' => 8, 'voice' => 3]))->toBeTrue()
        // A supervisor it is not asked about does not count.
        ->and($workers->differsFrom(['chat' => 6]))->toBeFalse();
});

it('finds no difference while Horizon is not running', function (): void {
    expect(new FakeHorizonWorkers([], [])->workers()->differsFrom(['chat' => 8]))->toBeFalse();
});

it('asks every running Horizon to restart through its command queue', function (): void {
    $horizon = new FakeHorizonWorkers(masters: ['web-1-abcd', 'web-2-efgh']);
    $workers = $horizon->workers();

    expect($workers->isRunning())->toBeTrue()
        ->and($workers->restart())->toBe(2)
        ->and($horizon->commands)->toBe([
            ['master:web-1-abcd', RestartMasterSupervisor::class],
            ['master:web-2-efgh', RestartMasterSupervisor::class],
        ]);
});

it('asks nobody while Horizon is not running', function (): void {
    $horizon = new FakeHorizonWorkers([], []);
    $workers = $horizon->workers();

    expect($workers->isRunning())->toBeFalse()
        ->and($workers->restart())->toBe(0)
        ->and($horizon->commands)->toBe([]);
});

it('sees Horizon stopping while its supervisors finish without a master', function (): void {
    expect(new FakeHorizonWorkers(masters: [])->workers()->isStopping())->toBeTrue();
});

it('does not see Horizon stopping while it runs, or once it is gone', function (array $masters, array $supervisors): void {
    expect(new FakeHorizonWorkers($masters, $supervisors)->workers()->isStopping())->toBeFalse();
})->with([
    'running' => [['web-1-abcd'], ['web-1-abcd:chat' => 6]],
    'gone' => [[], []],
]);
