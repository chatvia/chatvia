<?php

declare(strict_types=1);

namespace App\Console\Commands\Prune;

use App\Identity\Workspace\Actions\Sessions\PruneStaleKnownDevicesAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Scheduled clean-up of sign-in devices nobody used for a long time (see
 * {@see PruneStaleKnownDevicesAction}).
 */
#[Description('Delete sign-in devices (user_known_devices) not seen for '.PruneStaleKnownDevicesAction::STALE_AFTER_DAYS.' days.')]
#[Signature('chatvia:prune:known-devices')]
class PruneKnownDevices extends Command
{
    public function handle(PruneStaleKnownDevicesAction $prune): int
    {
        $deleted = $prune->execute();

        $this->info("Pruned {$deleted} stale sign-in device(s).");

        return self::SUCCESS;
    }
}
