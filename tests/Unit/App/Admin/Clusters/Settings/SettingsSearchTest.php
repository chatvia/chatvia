<?php

use App\Admin\Clusters\Settings\Data\SettingsSearchHitData;
use App\Admin\Clusters\Settings\Pages\ManageAiModelSettings;
use App\Admin\Clusters\Settings\SettingsSearch;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;

/**
 * The search of all Settings: the pages, the sections of each page and the
 * labels of their options. A find links to its page's section. The box on
 * the overview is in SettingsOverviewTest.
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

it('finds a section by its title, on its page, linked to the section', function (): void {
    $hit = app(SettingsSearch::class)->search('reranker')[0];

    expect($hit->entry->title)->toBe('Reranker')
        ->and($hit->entry->page)->toBe('AI models')
        ->and($hit->entry->url)->toBe(ManageAiModelSettings::getUrl().'#ai-models-reranker');
});

it('finds a section by the label of an option inside it, and names the option', function (): void {
    $hit = app(SettingsSearch::class)->search('chunk size')[0];

    expect($hit->entry->title)->toBe('Splitting')
        ->and($hit->options)->toBe(['Chunk size', 'Smallest chunk']);
});

it('needs every word, and lets what a page holds complete a section\'s match, never make one', function (): void {
    expect(ssFinds('reranker postmark'))->toBe([])
        // "Port" is an option of Sending; "SMTP" is only in what the Email page holds.
        ->and(ssFinds('smtp port'))->toBe(['Sending'])
        ->and(ssFinds('smtp'))->toBe(['Email']);
});

it('finds the pages too, first when their name matches, and ignores a single letter', function (): void {
    $hit = app(SettingsSearch::class)->search('ai models')[0];

    expect($hit->entry->title)->toBe('AI models')
        ->and($hit->entry->page)->toBeNull()
        ->and(ssFinds('a'))->toBe([]);
});

it('marks the words searched in an escaped text', function (): void {
    expect((string) app(SettingsSearch::class)->highlight('Keys & <b>key</b>', ['key']))
        ->toBe('<mark>Key</mark>s &amp; &lt;b&gt;<mark>key</mark>&lt;/b&gt;');
});
