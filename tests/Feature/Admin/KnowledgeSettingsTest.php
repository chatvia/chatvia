<?php

use App\Admin\Clusters\Settings\Pages\ManageKnowledgeSettings;
use App\Admin\Settings\KnowledgeSettings;
use App\Identity\Workspace\Models\User;
use Livewire\Livewire;

/**
 * The Knowledge page of the admin panel: what agents learn from. Splitting
 * and search have their own pages.
 */
beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create()->fresh();
});

it('shows what agents learn from, and leaves search to its own page', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->get(ManageKnowledgeSettings::getUrl())
        ->assertOk()
        ->assertSeeInOrder(['File types', 'Website import', 'AI enhancement'])
        ->assertDontSee(['Hybrid search', 'Follow-up questions', 'Fine-tuning', 'Try splitting']);
});

it('shows on the overview whether websites can be imported', function (): void {
    expect(app(ManageKnowledgeSettings::class)->overviewStatus())->toBe(['Website import off', 'gray']);

    $settings = app(KnowledgeSettings::class);
    $settings->firecrawl_api_key = 'fc-key';
    $settings->save();

    expect(app(ManageKnowledgeSettings::class)->overviewStatus())->toBe(['Website import on', 'success']);
});

it('keeps Firecrawl\'s address and rate in the card\'s Advanced part, and saves them', function (): void {
    Livewire::actingAs($this->admin, 'admin')
        ->test(ManageKnowledgeSettings::class)
        ->fillForm(['firecrawl_rate_per_minute' => 30])
        ->call('save')
        ->assertHasNoFormErrors();

    app()->forgetScopedInstances();

    expect(app(KnowledgeSettings::class)->firecrawl_rate_per_minute)->toBe(30);

    $this->actingAs($this->admin, 'admin')
        ->get(ManageKnowledgeSettings::getUrl())
        ->assertSee('Changed: Pages per minute.');
});
