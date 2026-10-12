<?php

declare(strict_types=1);

namespace App\Admin;

use Filament\Support\Colors\Color;

/**
 * The admin panel's colours, matching the Chatvia dashboard: neutral greys and
 * a black-and-white "ink" as the primary colour instead of a hue.
 *
 * Filament reads shades 600-700 of a palette for fills and text in light mode
 * and shades 400-500 in dark mode, so the ink palette jumps from near-white
 * (50-500) to near-black (600-950): black buttons, toggles and links in light
 * mode, white ones in dark mode. The panel theme
 * (resources/css/filament/admin/theme.css) sets the hover shades of buttons,
 * which a palette this steep cannot.
 */
final class PanelColors
{
    /** @var array<int, string> */
    public const array INK = [
        50 => 'oklch(0.985 0 0)',
        100 => 'oklch(0.97 0 0)',
        200 => 'oklch(0.945 0 0)',
        300 => 'oklch(0.922 0 0)',
        400 => 'oklch(0.9 0 0)',
        500 => 'oklch(0.87 0 0)',
        600 => 'oklch(0.205 0 0)',
        700 => 'oklch(0.18 0 0)',
        800 => 'oklch(0.16 0 0)',
        900 => 'oklch(0.145 0 0)',
        950 => 'oklch(0.1 0 0)',
    ];

    /**
     * @return array<string, array<int, string>>
     */
    public static function all(): array
    {
        return [
            'primary' => self::INK,
            'gray' => Color::Neutral,
            'success' => Color::Emerald,
            'warning' => Color::Amber,
            'danger' => Color::Red,
            'info' => Color::Sky,
        ];
    }
}
