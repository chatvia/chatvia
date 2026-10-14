<?php

declare(strict_types=1);

namespace App\Admin\Clusters\Settings\Data;

use App\Admin\Clusters\Settings\SettingsSearch;
use Spatie\LaravelData\Data;

/**
 * A page or section the Settings search found ({@see SettingsSearch}), with
 * the labels of its options that match and how well it matches: the
 * higher, the better.
 */
final class SettingsSearchHitData extends Data
{
    /**
     * @param  list<string>  $options  the labels of the section's options that match
     */
    public function __construct(
        public SettingsSearchEntryData $entry,
        public array $options,
        public int $score,
    ) {}
}
