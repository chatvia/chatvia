<?php

declare(strict_types=1);

namespace App\Admin\Widgets;

/**
 * Series colors for the admin dashboard's charts, used in this fixed order.
 * The first is the indigo the dashboard's own analytics charts use; the
 * orange beside it stays apart for colour-blind readers and reads on both
 * the light and the dark theme.
 */
final class ChartPalette
{
    public const string FIRST = '#6366f1';

    public const string SECOND = '#d95926';
}
