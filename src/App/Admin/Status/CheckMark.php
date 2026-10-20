<?php

declare(strict_types=1);

namespace App\Admin\Status;

/**
 * The mark in front of one line of a Status card: the thing checked is
 * fine, needs a look, is broken, is missing but not needed, or is just
 * told (a count, a setting, what a missing optional program costs) with
 * nothing to judge.
 */
enum CheckMark: string
{
    case Passed = 'passed';
    case Warning = 'warning';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case Info = 'info';

    public function icon(): string
    {
        return match ($this) {
            self::Passed => 'heroicon-m-check-circle',
            self::Warning => 'heroicon-m-exclamation-circle',
            self::Failed, self::Skipped => 'heroicon-m-x-circle',
            self::Info => 'heroicon-m-information-circle',
        };
    }
}
