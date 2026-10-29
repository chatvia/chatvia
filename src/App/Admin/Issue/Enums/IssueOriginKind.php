<?php

declare(strict_types=1);

namespace App\Admin\Issue\Enums;

/** What the app was doing when an issue happened. */
enum IssueOriginKind: string
{
    case Request = 'request';
    case Job = 'job';
    case Command = 'command';

    public function label(): string
    {
        return match ($this) {
            self::Request => 'Request',
            self::Job => 'Background job',
            self::Command => 'Command',
        };
    }
}
