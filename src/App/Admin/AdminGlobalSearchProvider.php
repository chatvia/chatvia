<?php

declare(strict_types=1);

namespace App\Admin;

use App\Admin\Clusters\Settings\Data\SettingsSearchHitData;
use App\Admin\Clusters\Settings\SettingsSearch;
use Filament\GlobalSearch\GlobalSearchResult;
use Filament\GlobalSearch\GlobalSearchResults;
use Filament\GlobalSearch\Providers\DefaultGlobalSearchProvider;
use Illuminate\Support\Arr;

/**
 * The search box of the panel's sidebar: what Filament finds in the
 * resources (workspaces by name), then the Settings pages and sections that
 * match, with the options that do ({@see SettingsSearch}).
 */
final class AdminGlobalSearchProvider extends DefaultGlobalSearchProvider
{
    /** The panel's search lists a few; the Settings overview lists them all. */
    private const int SETTINGS_LIMIT = 8;

    #[\Override]
    public function getResults(string $query): GlobalSearchResults
    {
        $results = parent::getResults($query) ?? GlobalSearchResults::make();
        $hits = app(SettingsSearch::class)->search($query, self::SETTINGS_LIMIT);

        if ($hits === []) {
            return $results;
        }

        return $results->category('Settings', array_map(fn (SettingsSearchHitData $hit): GlobalSearchResult => new GlobalSearchResult(
            title: $hit->entry->title,
            url: $hit->entry->url,
            details: array_filter([
                'Page' => $hit->entry->page,
                'Options' => $hit->options === [] ? null : Arr::join($hit->options, ', '),
            ]),
        ), $hits));
    }
}
