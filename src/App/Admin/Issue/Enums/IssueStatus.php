<?php

declare(strict_types=1);

namespace App\Admin\Issue\Enums;

/**
 * Where an admin stands with an issue. A resolved issue that happens again
 * opens again; an ignored one stays ignored and only adds to its count.
 */
enum IssueStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
    case Ignored = 'ignored';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Resolved => 'Resolved',
            self::Ignored => 'Ignored',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'danger',
            self::Resolved => 'success',
            self::Ignored => 'gray',
        };
    }
}
