<?php

declare(strict_types=1);

namespace App\Admin\Issue\Types;

use App\Admin\Issue\Data\IssueActionData;
use App\Admin\Issue\Data\IssueSubject;
use App\Admin\Pages\Status;
use Support\Radar\IssueTypeKey;

/**
 * A part of the install is down (the queue, the scheduler, Redis, live
 * updates…), as the Status page's checks found it. It closes when a later
 * check finds it running.
 */
final class ServiceStopped extends IssueType
{
    public function key(): string
    {
        return IssueTypeKey::SERVICE_STOPPED;
    }

    public function label(): string
    {
        return 'Down';
    }

    public function title(IssueSubject $subject): string
    {
        return "{$subject->service->label} is down";
    }

    public function advice(IssueSubject $subject): string
    {
        return 'Radar found it down while checking the install. The Status page shows what is wrong and how to start it again. The issue closes by itself once it runs.';
    }

    public function actions(IssueSubject $subject): array
    {
        return [new IssueActionData('Open Status', Status::getUrl())];
    }
}
