<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use Laravel\Horizon\MasterSupervisor;

/**
 * A command for Horizon's master process, sent through Horizon's command
 * queue in Redis ({@see HorizonWorkers::restart()}): Horizon runs it inside
 * the master. The master lets the running jobs finish, then exits, and its
 * process manager (Docker, Supervisor, systemd) starts it again, reading the
 * saved worker numbers. It exits with status 1, so a manager that restarts
 * only a failed process starts it too.
 */
final class RestartMasterSupervisor
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function process(MasterSupervisor $master, array $options): void
    {
        $master->terminate(1);
    }
}
