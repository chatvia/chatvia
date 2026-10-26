<?php

declare(strict_types=1);

namespace App\Admin\Backup\Enums;

/**
 * How often the automatic backup runs (Settings → Backups). A due check
 * every hour runs it once the newest scheduled backup is this old, so every
 * 48 hours and weekly work across month ends, and a run missed while the
 * server was down happens as soon as it is back.
 */
enum BackupFrequency: string
{
    case Every6Hours = '6h';
    case Every12Hours = '12h';
    case Daily = '24h';
    case Every2Days = '48h';
    case Weekly = 'week';

    public function hours(): int
    {
        return match ($this) {
            self::Every6Hours => 6,
            self::Every12Hours => 12,
            self::Daily => 24,
            self::Every2Days => 48,
            self::Weekly => 168,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Every6Hours => __('Every 6 hours'),
            self::Every12Hours => __('Every 12 hours'),
            self::Daily => __('Every 24 hours'),
            self::Every2Days => __('Every 48 hours'),
            self::Weekly => __('Every week'),
        };
    }
}
