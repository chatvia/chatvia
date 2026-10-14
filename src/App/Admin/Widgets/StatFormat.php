<?php

declare(strict_types=1);

namespace App\Admin\Widgets;

use Domain\Analytics\Data\Platform\CountTrendData;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

/**
 * How the admin panel's stats widgets word a trend and a reply time, so the
 * platform dashboard and a workspace's page read the same.
 */
final class StatFormat
{
    /** "12% more than the period before", in green or red. */
    public static function trend(Stat $stat, CountTrendData $trend): Stat
    {
        $change = $trend->changePct();

        if ($change === null) {
            return $stat->description('Nothing to compare with yet')->chartColor('primary');
        }

        return $stat
            ->description(Number::format(abs($change), maxPrecision: 1).'% '.($change >= 0 ? 'more' : 'fewer').' than the period before')
            ->descriptionIcon($change >= 0 ? Heroicon::ArrowTrendingUp : Heroicon::ArrowTrendingDown)
            ->descriptionColor($change >= 0 ? 'success' : 'danger')
            ->chartColor('primary');
    }

    public static function duration(?int $milliseconds): string
    {
        return match (true) {
            $milliseconds === null => '—',
            $milliseconds < 1000 => Number::format($milliseconds).' ms',
            default => Number::format($milliseconds / 1000, maxPrecision: 1).' s',
        };
    }
}
