<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Listeners;

use App\Identity\Workspace\Events\DataExportReady;
use App\Identity\Workspace\Notifications\DataExportReadyNotification;

/**
 * Tells the owner their data export can be downloaded.
 */
class SendDataExportReadyNotification
{
    public function handle(DataExportReady $event): void
    {
        $event->export->user->notify(
            new DataExportReadyNotification($event->export),
        );
    }
}
