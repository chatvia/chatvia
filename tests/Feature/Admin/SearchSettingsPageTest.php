<?php

use App\Admin\Clusters\Settings\Pages\ManageIngestionSettings;
use App\Admin\Clusters\Settings\Pages\ManageSearchSettings;
use App\Admin\Settings\Actions\CompleteSetupAction;
use App\Admin\Settings\Data\SetupData;
use App\Admin\Settings\SearchSettings;
use App\Identity\Workspace\Models\User;
use Domain\Knowledge\Embedding\Contracts\Reranker;
use Domain\Knowledge\Embedding\Rerank\NullReranker;
use Domain\Llm\Data\Catalog\RemoteModelData;
use Domain\Llm\Enums\LlmProviderEnum;
use Livewire\Livewire;

/**
 * The Search page of the admin panel: how agents search their knowledge
 * sources, on its own page apart from Ingestion.
 */
beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create()->fresh();
});

it('shows how results are found, the relevance check and follow-up questions', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->get(ManageSearchSettings::getUrl())
        ->assertOk()
        ->assertSeeInOrder([
            'Search', 'Keyword search on · 3 results per question · Balanced',
            'Relevance check', 'On · minimum relevance 0.5',
            'Follow-up questions', 'Rewrite questions off',
        ]);
});

it('turns the relevance check off while no reranker is set up', function (): void {
    app()->instance(Reranker::class, new NullReranker);

    $this->actingAs($this->admin, 'admin')
        ->get(ManageSearchSettings::getUrl())
        ->assertOk()
        ->assertSee('Needs a reranker');

    Livewire::actingAs($this->admin, 'admin')
        ->test(ManageSearchSettings::class)
        ->assertFormFieldDisabled('rerank')
        ->assertFormFieldDisabled('relevance_threshold');
});

it('is not part of the Ingestion page', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->get(ManageIngestionSettings::getUrl())
        ->assertOk()
        ->assertSeeInOrder(['Ingestion', 'File types', 'AI enhancement', 'Firecrawl'])
        ->assertDontSee(['Keyword search', 'Minimum relevance', 'Rewrite questions']);
});

it('saves the search options, and they reach the search', function (): void {
    Livewire::actingAs($this->admin, 'admin')
        ->test(ManageSearchSettings::class)
        ->fillForm([
            'hybrid_search' => false,
            'search_mode' => 'high_accuracy',
            'max_chunks' => 5,
            'relevance_threshold' => 0.4,
            'query_rewriter' => true,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    app()->forgetScopedInstances();

    expect(app(SearchSettings::class)->max_chunks)->toBe(5)
        ->and(config('chatvia.vector.search.hybrid'))->toBeFalse()
        ->and(config('chatvia.vector.search.mode'))->toBe('high_accuracy')
        ->and(config('chatvia.vector.search.max_chunks'))->toBe(5)
        ->and(config('chatvia.vector.search.relevance_threshold'))->toBe(0.4)
        ->and(config('chatvia.ai.query.rewriter.enabled'))->toBeTrue();
});

it('takes the keyword search choice of the admin setup', function (): void {
    app(CompleteSetupAction::class)->execute(new SetupData(
        chatKind: LlmProviderEnum::OPENROUTER,
        chatBaseUrl: null,
        chatApiKey: 'sk-test',
        chatModel: new RemoteModelData('openai/gpt-oss-120b', 'openai/gpt-oss-120b'),
        hybridSearch: false,
    ));

    app()->forgetScopedInstances();

    expect(app(SearchSettings::class)->hybrid_search)->toBeFalse()
        ->and(config('chatvia.vector.search.hybrid'))->toBeFalse();
});
