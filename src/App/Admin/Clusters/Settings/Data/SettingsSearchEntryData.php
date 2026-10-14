<?php

declare(strict_types=1);

namespace App\Admin\Clusters\Settings\Data;

use App\Admin\Clusters\Settings\SettingsSearch;
use BackedEnum;
use Spatie\LaravelData\Data;

/**
 * One thing the Settings search can find ({@see SettingsSearch}): a Settings
 * page, or a section (card) of one with the labels of its options. A
 * section's link opens the page on that section.
 */
final class SettingsSearchEntryData extends Data
{
    /**
     * @param  string|null  $page  the name of the page a section is on; null for a page itself
     * @param  string|BackedEnum|null  $icon  an icon, or the URL of a service's logo
     * @param  list<string>  $options  the labels of the section's options
     * @param  string|null  $pageDescription  what the page a section is on holds
     */
    public function __construct(
        public string $title,
        public ?string $page,
        public string $description,
        public string $url,
        public string|BackedEnum|null $icon = null,
        public array $options = [],
        public ?string $pageDescription = null,
    ) {}
}
