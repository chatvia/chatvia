<?php

use App\Admin\Clusters\Settings\Pages\ManageKnowledgeSettings;
use App\Admin\Settings\KnowledgeSettings;
use App\Identity\Workspace\Models\User;
use Domain\Knowledge\Embedding\Contracts\Reranker;
use Domain\Knowledge\Embedding\Rerank\NullReranker;
use Livewire\Livewire;

/**
 * The Knowledge page of the admin panel: what agents learn from and how
 * they search it. Everyday cards first; splitting and the fine-tuning of
 * search come last, under Advanced, closed.
 */
beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create()->fresh();
});

it('shows the everyday cards first, and the fine-tuning under Advanced', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->get(ManageKnowledgeSettings::getUrl())
        ->assertOk()
        ->assertSeeInOrder([
            'File types', 'Website import', 'AI enhancement',
            'Search', 'Keyword search on · 5 results per question',
            'Follow-up questions', 'Understand follow-up questions on',
            'Technical options. The defaults suit most installs.',
            'Splitting', 'Try splitting',
            'Search fine-tuning', 'Balanced · reranker, minimum 0.3',
        ]);
});

it('uses the basic similarity check while no reranker is set up', function (): void {
    app()->instance(Reranker::class, new NullReranker);
    config(['chatvia.ai.models.embedding' => 'qwen']);

    $this->actingAs($this->admin, 'admin')
        ->get(ManageKnowledgeSettings::getUrl())
        ->assertOk()
        ->assertSee('Balanced · basic check, minimum 0.35');

    Livewire::actingAs($this->admin, 'admin')
        ->test(ManageKnowledgeSettings::class)
        ->assertFormFieldEnabled('relevance_check')
        ->assertFormFieldDisabled('relevance_threshold')
        ->assertFormFieldEnabled('similarity_threshold');
});

it('says when there is nothing to check with', function (): void {
    app()->instance(Reranker::class, new NullReranker);
    config(['chatvia.ai.models.embedding' => 'openai']);

    $this->actingAs($this->admin, 'admin')
        ->get(ManageKnowledgeSettings::getUrl())
        ->assertSee('Balanced · no check measured');
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

it('saves the search options, and they reach the search', function (): void {
    Livewire::actingAs($this->admin, 'admin')
        ->test(ManageKnowledgeSettings::class)
        ->fillForm([
            'hybrid_search' => false,
            'search_mode' => 'high_accuracy',
            'max_chunks' => 6,
            'max_context_tokens' => 4000,
            'relevance_check' => false,
            'relevance_threshold' => 0.4,
            'similarity_threshold' => 0.42,
            'query_rewriter' => false,
            'qa_similarity_threshold' => 0.9,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    app()->forgetScopedInstances();

    expect(app(KnowledgeSettings::class)->max_chunks)->toBe(6)
        ->and(config('chatvia.vector.search.hybrid'))->toBeFalse()
        ->and(config('chatvia.vector.search.mode'))->toBe('high_accuracy')
        ->and(config('chatvia.vector.search.max_chunks'))->toBe(6)
        ->and(config('chatvia.vector.search.max_context_tokens'))->toBe(4000)
        ->and(config('chatvia.vector.search.relevance_check'))->toBeFalse()
        ->and(config('chatvia.vector.search.relevance_threshold'))->toBe(0.4)
        ->and(config('chatvia.vector.search.similarity_threshold'))->toBe(0.42)
        ->and(config('chatvia.ai.query.rewriter.enabled'))->toBeFalse()
        ->and(config('chatvia.vector.search.qa_similarity_threshold'))->toBe(0.9);
});
