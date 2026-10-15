<?php

declare(strict_types=1);

namespace App\Admin\Status\Data;

use Filament\Support\Icons\Heroicon;
use Spatie\LaravelData\Data;

/**
 * One card of the Status page's environment report (the application, the
 * versions, the caches or the drivers), with its lines.
 */
class EnvironmentGroupData extends Data
{
    /**
     * @param  list<EnvironmentFactData>  $facts
     */
    public function __construct(
        public string $title,
        public Heroicon $icon,
        public array $facts,
    ) {}
}
