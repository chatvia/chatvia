<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Whether a settings group has the values it needs, and where they come from.
 */
enum SettingStatus: string implements HasColor, HasIcon, HasLabel
{
    /** Every required value is set, at least one of them saved in the panel. */
    case Configured = 'configured';

    /** Every required value is set, all of them from the .env file. */
    case FromEnv = 'env';

    /** Some required values are set, some are missing. */
    case Incomplete = 'incomplete';

    /** Nothing is set. */
    case Missing = 'missing';

    public function getLabel(): string
    {
        return match ($this) {
            self::Configured => 'Configured',
            self::FromEnv => 'From .env',
            self::Incomplete => 'Incomplete',
            self::Missing => 'Not set',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Configured => 'success',
            self::FromEnv => 'info',
            self::Incomplete => 'warning',
            self::Missing => 'gray',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Configured => Heroicon::OutlinedCheckCircle,
            self::FromEnv => Heroicon::OutlinedDocumentText,
            self::Incomplete => Heroicon::OutlinedExclamationTriangle,
            self::Missing => Heroicon::OutlinedMinusCircle,
        };
    }

    public function isUsable(): bool
    {
        return $this === self::Configured || $this === self::FromEnv;
    }
}
