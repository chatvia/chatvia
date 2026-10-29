<?php

use App\Admin\Clusters\Settings\Data\SettingsSearchHitData;
use App\Admin\Clusters\Settings\SettingsSearch;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;

/**
 * The search of all Settings: every word must match, what a page holds
 * only completes a section's match, and the searched words are marked in
 * escaped text. The box on the overview is in SettingsOverviewTest.
 */
beforeEach(function (): void {
    Auth::guard('admin')->login(adminUser());
    Filament::setCurrentPanel('admin');
});

/**
 * The titles of what a question finds, best first.
 *
 * @return list<string>
 */
function ssFinds(string $query): array
{
    return array_map(fn (SettingsSearchHitData $hit): string => $hit->entry->title, app(SettingsSearch::class)->search($query));
}

it('finds only what matches every word, lets what a page holds complete a match but never make one, and ignores a single letter', function (): void {
    expect(ssFinds('reranker postmark'))->toBe([])
        // "Port" is an option of Sending; "SMTP" is only in what the Email page holds.
        ->and(ssFinds('smtp port'))->toBe(['Sending'])
        ->and(ssFinds('smtp'))->toBe(['Email'])
        ->and(ssFinds('a'))->toBe([]);
});

it('marks the words searched in an escaped text', function (): void {
    expect((string) app(SettingsSearch::class)->highlight('Keys & <b>key</b>', ['key']))
        ->toBe('<mark>Key</mark>s &amp; &lt;b&gt;<mark>key</mark>&lt;/b&gt;');
});
