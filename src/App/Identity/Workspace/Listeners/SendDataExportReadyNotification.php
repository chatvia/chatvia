<?php

namespace App\Identity\Workspace\Listeners;

use App\Identity\Workspace\Events\DataExportReady;
use App\Identity\Workspace\Notifications\DataExportReadyNotification;

class SendDataExportReadyNotification
{
    public function handle(DataExportReady $event): void
    {
        $event->export->user->notify(
            new DataExportReadyNotification($event->export),
        );
    }
}
