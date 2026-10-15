<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use Illuminate\Support\Str;
use Laravel\Horizon\Contracts\HorizonCommandQueue;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Laravel\Horizon\MasterSupervisor;

/**
 * The queue workers Horizon runs now, for Settings → Platform → Queue
 * workers. Horizon reads the worker numbers only when it starts, so the
 * panel compares them with the saved ones ({@see differsFrom()}) and can
 * restart Horizon ({@see restart()}).
 *
 * The restart goes through Horizon's own command queue in Redis, not
 * `horizon:terminate`: that command signals processes by PID on this host
 * only, and in Docker Horizon runs in another container.
 */
final readonly class HorizonWorkers
{
    public function __construct(
        private MasterSupervisorRepository $masters,
        private SupervisorRepository $supervisors,
        private HorizonCommandQueue $commands,
    ) {}

    /** Whether a Horizon is running for this app (on any server). */
    public function isRunning(): bool
    {
        return $this->masters->names() !== [];
    }

    /**
     * Whether Horizon is stopping: its master is gone, but some of its
     * supervisors still finish their running jobs. That is how a restart
     * looks until the process manager starts Horizon again.
     */
    public function isStopping(): bool
    {
        return ! $this->isRunning() && $this->supervisors->names() !== [];
    }

    /**
     * Whether a running supervisor may run another number of workers than the
     * one given for it. Supervisors not given are left out.
     *
     * @param  array<string, int>  $workers  The most workers by supervisor name (chat, ingestion…).
     */
    public function differsFrom(array $workers): bool
    {
        foreach ($this->supervisors->all() as $supervisor) {
            // Horizon names a supervisor "{master}:{name}".
            $name = Str::afterLast((string) $supervisor->name, ':');

            if (array_key_exists($name, $workers) && (int) ($supervisor->options['maxProcesses'] ?? 0) !== $workers[$name]) {
                return true;
            }
        }

        return false;
    }

    /**
     * Asks every running Horizon to restart ({@see RestartMasterSupervisor}).
     * Each one picks the command up within a second, lets its running jobs
     * finish and exits; its process manager starts it again.
     *
     * @return int How many Horizons were asked.
     */
    public function restart(): int
    {
        $names = $this->masters->names();

        foreach ($names as $name) {
            $this->commands->push(MasterSupervisor::commandQueueFor($name), RestartMasterSupervisor::class);
        }

        return count($names);
    }
}
