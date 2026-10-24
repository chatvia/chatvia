<?php

declare(strict_types=1);

namespace Tests\Support\Admin;

use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;

/**
 * The data a chart widget of the admin panel hands to the browser: Filament
 * writes it into the page as `cachedData: JSON.parse('…')`. Reading it from
 * the page tests what the chart shows, without reaching into the widget.
 */
final class ChartData
{
    /** @return array<string, mixed> The chart's `datasets` and `labels`. */
    public static function of(Testable $widget): array
    {
        $literal = Str::betweenFirst($widget->html(), "cachedData: JSON.parse('", "')");

        // A JavaScript string of JSON whose quotes are written as ".
        return json_decode((string) json_decode('"'.$literal.'"'), true, flags: JSON_THROW_ON_ERROR);
    }
}
