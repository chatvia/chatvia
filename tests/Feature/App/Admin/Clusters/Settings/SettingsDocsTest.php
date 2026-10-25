<?php

use App\Admin\Clusters\Settings\AdminSettingsPage;
use App\Admin\Clusters\Settings\SettingsCard;
use App\Admin\Clusters\Settings\SettingsCluster;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * Every settings card links to its heading in the admin panel docs, which
 * live in the chatvia-landing repository
 * (docs/guide/admin-panel/settings/{page}.md). The fixture lists each card's
 * docs address and its options, so adding, renaming or removing a card or
 * an option fails here until the docs page is updated too. After updating
 * the docs, rewrite the fixture with SETTINGS_DOCS_WRITE=1.
 */
const SETTINGS_DOCS_FIXTURE = __DIR__.'/../../../../../Fixtures/settings/docs-contract.json';

/**
 * Each card of every Settings page, hidden ones too, by its docs address,
 * with the labels of its options.
 *
 * @return array<string, list<string>>
 */
function sdCards(): array
{
    $admin = adminUser();
    $cards = [];

    foreach (SettingsCluster::getClusteredComponents() as $page) {
        if (! is_subclass_of($page, AdminSettingsPage::class)) {
            continue;
        }

        $instance = Livewire::actingAs($admin, 'admin')->test($page)->instance();

        // optionLabels() is private to the base page, so the closure takes its scope.
        $labels = Closure::bind(fn (SettingsCard $card): array => $this->optionLabels($card), $instance, AdminSettingsPage::class);

        foreach ((fn (): array => $this->cards())->call($instance) as $card) {
            /** @var SettingsCard $card */
            $cards[Str::after($instance->docsUrl($card), '/settings/')] = $labels($card);
        }
    }

    ksort($cards);

    return $cards;
}

it('keeps the admin panel docs in step with the settings cards', function (): void {
    $cards = sdCards();

    if (getenv('SETTINGS_DOCS_WRITE')) {
        File::ensureDirectoryExists(dirname(SETTINGS_DOCS_FIXTURE));
        File::put(SETTINGS_DOCS_FIXTURE, json_encode($cards, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
    }

    $documented = json_decode(File::get(SETTINGS_DOCS_FIXTURE), true);
    $changed = collect(array_keys($cards + $documented))
        ->filter(fn (string $card): bool => ($cards[$card] ?? null) !== ($documented[$card] ?? null))
        ->values()
        ->all();

    expect($changed)->toBe([], 'These settings cards changed: '.implode(', ', $changed).'. Update their pages in chatvia-landing/docs/guide/admin-panel/settings/ '
        .'(a card\'s title is its heading there, and every option is explained), then rewrite '
        .'tests/Fixtures/settings/docs-contract.json with SETTINGS_DOCS_WRITE=1.');
});
