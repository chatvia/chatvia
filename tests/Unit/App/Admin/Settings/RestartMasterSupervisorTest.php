<?php

use App\Admin\Settings\RestartMasterSupervisor;
use Laravel\Horizon\MasterSupervisor;

/**
 * The command Horizon's master runs to restart: it exits with a failure
 * status, so a process manager that restarts only a failed process starts
 * it again too.
 */
it('stops the master with a status every process manager restarts', function (): void {
    $master = Mockery::mock(MasterSupervisor::class);
    $master->expects('terminate')->with(1);

    new RestartMasterSupervisor()->process($master);
});
