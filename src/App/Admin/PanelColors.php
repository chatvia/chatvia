<?php

declare(strict_types=1);

namespace App\Admin;

use Filament\Support\Colors\Color;

/**
 * The admin panel's colours, matching the Chatvia dashboard: neutral greys and
 * Tailwind's indigo as the primary colour, the same shades the dashboard's
 * `--primary` uses (resources/css/app.css). Filament fills with shade 600 in
 * light mode and 500 in dark mode, as the dashboard does. The panel theme
 * (resources/css/filament/admin/theme.css) reads these shades for its own
 * buttons, links and focus rings.
 */
final class PanelColors
{
    /**
     * @return array<string, array<int, string>>
     */
    public static function all(): array
    {
        return [
            'primary' => Color::Indigo,
            'gray' => Color::Neutral,
            'success' => Color::Emerald,
            'warning' => Color::Amber,
            'danger' => Color::Red,
            'info' => Color::Sky,
        ];
    }
}
