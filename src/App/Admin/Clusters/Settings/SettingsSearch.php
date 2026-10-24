<?php

declare(strict_types=1);

namespace App\Admin\Clusters\Settings;

use App\Admin\AdminGlobalSearchProvider;
use App\Admin\Clusters\Settings\Data\SettingsSearchEntryData;
use App\Admin\Clusters\Settings\Data\SettingsSearchHitData;
use App\Admin\Clusters\Settings\Pages\SettingsOverview;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Searches all of Settings: each page, each section (card) of a page, and
 * the labels of a section's options. Every word typed must appear in the
 * title, the page's name, the description or an option's label; a word
 * found only in what the page says it holds counts too, once another word
 * is found in the section itself. A title counts most, then an option, the
 * page and the description, and a word that starts a word counts more than
 * one inside a word. Ties keep the order of the Settings overview.
 *
 * The search box of the Settings overview and the panel's own search
 * ({@see AdminGlobalSearchProvider}) use it.
 */
final class SettingsSearch
{
    /** Fewer letters than this find too much to help. */
    private const int MIN_LENGTH = 2;

    /**
     * @return list<SettingsSearchHitData> best first
     */
    public function search(string $query, int $limit = 30): array
    {
        $terms = $this->terms($query);

        if ($terms === []) {
            return [];
        }

        return collect($this->entries())
            ->map(fn (SettingsSearchEntryData $entry): ?SettingsSearchHitData => $this->match($entry, $terms))
            ->filter()
            ->sortByDesc(fn (SettingsSearchHitData $hit): int => $hit->score)
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * The words of a question, lower case; none when it is too short.
     *
     * @return list<string>
     */
    public function terms(string $query): array
    {
        $query = Str::lower(Str::squish($query));

        if (Str::length($query) < self::MIN_LENGTH) {
            return [];
        }

        return Str::of($query)->explode(' ')->unique()->values()->all();
    }

    /**
     * The text, escaped, with each word of the question marked.
     *
     * @param  list<string>  $terms
     */
    public function highlight(string $text, array $terms): HtmlString
    {
        if ($terms === []) {
            return new HtmlString(e($text));
        }

        $pattern = '/('.implode('|', array_map(fn (string $term): string => preg_quote($term, '/'), $terms)).')/iu';
        $parts = Str::of($text)
            ->split($pattern, flags: PREG_SPLIT_DELIM_CAPTURE)
            ->whenEmpty(fn (Collection $parts): Collection => $parts->push($text));

        // Split on the words, so the marks never land inside an escaped character.
        return new HtmlString($parts
            ->map(fn (string $part, int $index): string => $index % 2 === 1 ? '<mark>'.e($part).'</mark>' : e($part))
            ->implode(''));
    }

    /**
     * Every page the admin can open, in the order of the overview, each with
     * its sections.
     *
     * @return list<SettingsSearchEntryData>
     */
    private function entries(): array
    {
        return collect(SettingsCluster::getClusteredComponents())
            ->filter(fn (string $component): bool => is_subclass_of($component, AdminSettingsPage::class) && $component::canAccess())
            ->sortBy([
                fn (string $a, string $b): int => array_search($a::overviewGroup(), SettingsOverview::GROUPS, true) <=> array_search($b::overviewGroup(), SettingsOverview::GROUPS, true),
                fn (string $a, string $b): int => ($a::getNavigationSort() ?? 0) <=> ($b::getNavigationSort() ?? 0),
            ])
            ->flatMap(fn (string $page): array => app($page)->searchEntries())
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $terms
     */
    private function match(SettingsSearchEntryData $entry, array $terms): ?SettingsSearchHitData
    {
        $score = 0;
        $ownWords = 0;

        foreach ($terms as $term) {
            $points = max(
                $this->points($entry->title, $term, 8),
                $this->points($entry->page ?? '', $term, 2),
                $this->points($entry->description, $term, 1),
                ...array_map(fn (string $option): int => $this->points($option, $term, 4), $entry->options),
            );

            if ($points > 0) {
                $ownWords++;
            } elseif (Str::contains($entry->pageDescription ?? '', $term, ignoreCase: true)) {
                // What the page says it holds completes a section's match ("smtp port"), never makes one.
                $points = 1;
            } else {
                return null;
            }

            $score += $points;
        }

        if ($ownWords === 0) {
            return null;
        }

        $options = array_values(array_filter(
            $entry->options,
            fn (string $option): bool => Str::lower($option) !== Str::lower($entry->title)
                && array_any($terms, fn (string $term): bool => Str::contains($option, $term, ignoreCase: true)),
        ));

        return new SettingsSearchHitData($entry, $options, $score);
    }

    /** The weight when the word starts a word of the text, a little less inside a word, else 0. */
    private function points(string $text, string $term, int $weight): int
    {
        return match (true) {
            preg_match('/(?<![\p{L}\p{N}])'.preg_quote($term, '/').'/iu', $text) === 1 => $weight * 2,
            Str::contains($text, $term, ignoreCase: true) => $weight * 2 - 1,
            default => 0,
        };
    }
}
