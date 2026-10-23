<?php

use App\Admin\Settings\HorizonWorkers;
use App\Admin\Settings\RestartMasterSupervisor;
use Laravel\Horizon\Contracts\HorizonCommandQueue;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Laravel\Horizon\MasterSupervisor;

/**
 * The queue workers Horizon runs now: the panel compares their numbers with
 * the saved ones, and restarts Horizon through its command queue in Redis,
 * which also reaches a Horizon in another container or on another server.
 */
beforeEach(function (): void {
    $this->masters = Mockery::mock(MasterSupervisorRepository::class);
    $this->supervisors = Mockery::mock(SupervisorRepository::class);
    $this->commands = Mockery::mock(HorizonCommandQueue::class);
    $this->workers = new HorizonWorkers($this->masters, $this->supervisors, $this->commands);
});

/** A supervisor as Horizon stores it in Redis, named "{master}:{supervisor}". */
function runningSupervisor(string $name, int $maxProcesses): object
{
    return (object) ['name' => "web-1-abcd:{$name}", 'options' => ['maxProcesses' => $maxProcesses]];
}

it('finds a running supervisor that uses another number of workers', function (): void {
    $this->supervisors->allows('all')->andReturn([runningSupervisor('chat', 6), runningSupervisor('voice', 3)]);

    expect($this->workers->differsFrom(['chat' => 6, 'voice' => 3]))->toBeFalse()
        ->and($this->workers->differsFrom(['chat' => 8, 'voice' => 3]))->toBeTrue()
        // A supervisor it is not asked about does not count.
        ->and($this->workers->differsFrom(['chat' => 6]))->toBeFalse();
});

it('finds no difference while Horizon is not running', function (): void {
    $this->supervisors->allows('all')->andReturn([]);

    expect($this->workers->differsFrom(['chat' => 8]))->toBeFalse();
});

it('asks every running Horizon to restart through its command queue', function (): void {
    $this->masters->allows('names')->andReturn(['web-1-abcd', 'web-2-efgh']);
    $this->commands->expects('push')->with('master:web-1-abcd', RestartMasterSupervisor::class);
    $this->commands->expects('push')->with('master:web-2-efgh', RestartMasterSupervisor::class);

    expect($this->workers->isRunning())->toBeTrue()
        ->and($this->workers->restart())->toBe(2);
});

it('asks nobody while Horizon is not running', function (): void {
    $this->masters->allows('names')->andReturn([]);
    $this->commands->shouldNotReceive('push');

    expect($this->workers->isRunning())->toBeFalse()
        ->and($this->workers->restart())->toBe(0);
});

it('sees Horizon stopping while its supervisors finish without a master', function (): void {
    $this->masters->allows('names')->andReturn([]);
    $this->supervisors->allows('names')->andReturn(['web-1-abcd:chat']);

    expect($this->workers->isStopping())->toBeTrue();
});

it('does not see Horizon stopping while it runs, or once it is gone', function (array $masters, array $supervisors): void {
    $this->masters->allows('names')->andReturn($masters);
    $this->supervisors->allows('names')->andReturn($supervisors);

    expect($this->workers->isStopping())->toBeFalse();
})->with([
    'running' => [['web-1-abcd'], ['web-1-abcd:chat']],
    'gone' => [[], []],
]);

it('stops the master with a status every process manager restarts', function (): void {
    $master = Mockery::mock(MasterSupervisor::class);
    $master->expects('terminate')->with(1);

    (new RestartMasterSupervisor)->process($master);
});
