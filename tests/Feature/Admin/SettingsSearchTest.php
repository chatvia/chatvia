<?php

use App\Admin\AdminGlobalSearchProvider;
use App\Admin\Clusters\Settings\Data\SettingsSearchHitData;
use App\Admin\Clusters\Settings\Pages\ManageAiModelSettings;
use App\Admin\Clusters\Settings\Pages\ManageKnowledgeSettings;
use App\Admin\Clusters\Settings\Pages\SettingsOverview;
use App\Admin\Clusters\Settings\SettingsSearch;
use App\Identity\Workspace\Models\User;
use Filament\Facades\Filament;
use Filament\GlobalSearch\GlobalSearchResult;
use Livewire\Livewire;

/**
 * The search of all Settings: the pages, the sections of each page and the
 * labels of their options, in the box on the Settings overview and in the
 * panel's own search. A find opens its page on its section.
 */
beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create()->fresh(), 'admin');
    Filament::setCurrentPanel('admin');
});

/**
 * The titles of what a question finds, best first.
 *
 * @return list<string>
 */
function settingsFinds(string $query): array
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
        ->and($hit->options)->toBe(['Chunk size']);
});

it('needs every word, and lets what a page holds complete a section\'s match, never make one', function (): void {
    expect(settingsFinds('reranker postmark'))->toBe([])
        // "Port" is an option of Sending; "SMTP" is only in what the Email page holds.
        ->and(settingsFinds('smtp port'))->toBe(['Sending'])
        ->and(settingsFinds('smtp'))->toBe(['Email']);
});

it('finds the pages too, first when their name matches, and ignores a single letter', function (): void {
    $hit = app(SettingsSearch::class)->search('ai models')[0];

    expect($hit->entry->title)->toBe('AI models')
        ->and($hit->entry->page)->toBeNull()
        ->and(settingsFinds('a'))->toBe([]);
});

it('marks the words searched in an escaped text', function (): void {
    expect((string) app(SettingsSearch::class)->highlight('Keys & <b>key</b>', ['key']))
        ->toBe('<mark>Key</mark>s &amp; &lt;b&gt;<mark>key</mark>&lt;/b&gt;');
});

it('shows the finds in place of the tiles on the overview, and says when there are none', function (): void {
    Livewire::test(SettingsOverview::class)
        ->assertSee('Sign-in and people')
        ->set('search', 'chunk size')
        ->assertDontSee('Sign-in and people')
        ->assertSee(['1 result', 'Settings › Knowledge'])
        ->assertSeeHtml(['<mark>Chunk</mark> <mark>size</mark>', ManageKnowledgeSettings::getUrl().'#knowledge-splitting'])
        ->set('search', 'qqqq')
        ->assertSee('Nothing in Settings matches “qqqq”');
});

it('adds the Settings to the panel\'s own search, after the workspaces', function (): void {
    expect(Filament::getPanel('admin')->getGlobalSearchProvider())->toBeInstanceOf(AdminGlobalSearchProvider::class);

    $settings = app(AdminGlobalSearchProvider::class)->getResults('reranker')->getCategories()['Settings'];

    expect($settings[0])->toBeInstanceOf(GlobalSearchResult::class)
        ->and($settings[0]->title)->toBe('Reranker')
        ->and($settings[0]->url)->toBe(ManageAiModelSettings::getUrl().'#ai-models-reranker')
        ->and($settings[0]->details)->toBe(['Page' => 'AI models']);
});
