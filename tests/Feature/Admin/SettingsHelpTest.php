<?php

use App\Admin\Clusters\Settings\AdminSettingsPage;
use App\Admin\Clusters\Settings\Pages\ManageSearchSettings;
use App\Admin\Clusters\Settings\Pages\ManageSignInSettings;
use App\Admin\Clusters\Settings\SettingsCard;
use App\Admin\Clusters\Settings\SettingsCluster;
use App\Identity\Workspace\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

/**
 * Every card on the Settings pages has a "?" button that explains its options
 * in plain words, from a Markdown file per card.
 */
beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create()->fresh();
});

/**
 * Every card of every Settings page, by page, whatever its visibility.
 *
 * @return array<class-string<AdminSettingsPage>, array{0: AdminSettingsPage, 1: list<SettingsCard>}>
 */
function everySettingsCard(User $admin): array
{
    $cards = [];

    foreach (SettingsCluster::getClusteredComponents() as $page) {
        if (! is_subclass_of($page, AdminSettingsPage::class)) {
            continue;
        }

        $instance = Livewire::actingAs($admin, 'admin')->test($page)->instance();
        $cards[$page] = [$instance, (fn (): array => $this->cards())->call($instance)];
    }

    return $cards;
}

it('explains every card of every settings page', function (): void {
    $missing = [];

    foreach (everySettingsCard($this->admin) as $page => [$instance, $cards]) {
        foreach ($cards as $card) {
            $help = $instance->helpOf($card);

            if (blank($help) || str_contains($help, '{title}')) {
                $missing[] = $page::getSlug().'/'.($card->helpFile ?? $card->key).'.md';
            }
        }
    }

    expect($missing)->toBe([], 'Add a plain-language explanation in resources/help/settings/ for: '.implode(', ', $missing));
});

it('opens the explanation from the card\'s "?" button', function (): void {
    Livewire::actingAs($this->admin, 'admin')
        ->test(ManageSearchSettings::class)
        ->assertSee('What do these options do?')
        ->mountAction(TestAction::make('help_finding')->schemaComponent('help-search-finding', schema: 'form'))
        ->assertActionMounted(TestAction::make('help_finding')->schemaComponent('help-search-finding', schema: 'form'))
        ->assertMountedActionModalSee('by meaning and by keywords together');
});

it('fills in the name of the card in a shared explanation', function (): void {
    [$instance, $cards] = everySettingsCard($this->admin)[ManageSignInSettings::class];
    $github = collect($cards)->firstWhere('key', 'github');

    expect($instance->helpOf($github))->toContain('GitHub')->not->toContain('{title}');
});
