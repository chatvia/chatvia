<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Whether a settings group has the values it needs.
 */
enum SettingStatus: string implements HasColor, HasIcon, HasLabel
{
    /** Every required value is saved. */
    case Configured = 'configured';

    /** Some values are saved, but a required one is missing. */
    case Incomplete = 'incomplete';

    /** Nothing is saved. */
    case Missing = 'missing';

    public function getLabel(): string
    {
        return match ($this) {
            self::Configured => 'Configured',
            self::Incomplete => 'Incomplete',
            self::Missing => 'Not set',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Configured => 'success',
            self::Incomplete => 'warning',
            self::Missing => 'gray',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Configured => Heroicon::OutlinedCheckCircle,
            self::Incomplete => Heroicon::OutlinedExclamationTriangle,
            self::Missing => Heroicon::OutlinedMinusCircle,
        };
    }
}
