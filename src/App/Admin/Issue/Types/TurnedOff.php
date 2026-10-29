<?php

declare(strict_types=1);

namespace App\Admin\Issue\Types;

use App\Admin\Issue\Data\IssueSubject;
use Support\Radar\IssueTypeKey;

/** A model in use belongs to a provider an admin turned off. */
final class TurnedOff extends IssueType
{
    public function key(): string
    {
        return IssueTypeKey::TURNED_OFF;
    }

    public function label(): string
    {
        return 'Turned off';
    }

    public function title(IssueSubject $subject): string
    {
        return "{$subject->service->label} is turned off, but a model still uses it";
    }

    public function advice(IssueSubject $subject): string
    {
        return "{$subject->service->label} is turned off in Settings → AI providers, but an agent or an app task still uses one of its models. Turn it on, or pick another model for them.";
    }
}
