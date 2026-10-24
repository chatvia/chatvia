<?php

declare(strict_types=1);

namespace Tests\Support\Admin;

use App\Admin\Settings\HorizonWorkers;
use Laravel\Horizon\Contracts\HorizonCommandQueue;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Mockery;

/**
 * The Horizon that HorizonWorkers reads, kept in memory instead of Redis:
 * the masters running, the supervisors with their worker numbers, and the
 * commands sent to the masters. A test changes the public lists to change
 * what Horizon reports, and reads {@see $commands} to see what was sent.
 */
final class FakeHorizonWorkers
{
    /** @var list<array{0: string, 1: string}> Each command sent: the master's command queue and the command class. */
    public array $commands = [];

    /**
     * @param  list<string>  $masters  The masters running, as Horizon names them ("web-1-abcd").
     * @param  array<string, int>  $supervisors  The most workers of each supervisor, by its full name ("web-1-abcd:chat").
     */
    public function __construct(
        public array $masters = ['web-1-abcd'],
        public array $supervisors = ['web-1-abcd:chat' => 6],
    ) {}

    /**
     * A fake Horizon, bound as the one the panel and the commands read.
     *
     * @param  list<string>  $masters
     * @param  array<string, int>  $supervisors
     */
    public static function bind(array $masters = ['web-1-abcd'], array $supervisors = ['web-1-abcd:chat' => 6]): self
    {
        $horizon = new self($masters, $supervisors);

        app()->instance(HorizonWorkers::class, $horizon->workers());

        return $horizon;
    }

    /** A Horizon that is not running: no master, no supervisor. */
    public static function stopped(): self
    {
        return self::bind([], []);
    }

    public function workers(): HorizonWorkers
    {
        $masters = Mockery::mock(MasterSupervisorRepository::class);
        $masters->allows('names')->andReturnUsing(fn (): array => $this->masters);

        $supervisors = Mockery::mock(SupervisorRepository::class);
        $supervisors->allows('names')->andReturnUsing(fn (): array => array_keys($this->supervisors));
        $supervisors->allows('all')->andReturnUsing(fn (): array => array_map(
            fn (string $name, int $workers): object => (object) ['name' => $name, 'options' => ['maxProcesses' => $workers]],
            array_keys($this->supervisors),
            $this->supervisors,
        ));

        $commands = Mockery::mock(HorizonCommandQueue::class);
        $commands->allows('push')->andReturnUsing(function (string $queue, string $command): void {
            $this->commands[] = [$queue, $command];
        });

        return new HorizonWorkers($masters, $supervisors, $commands);
    }
}
