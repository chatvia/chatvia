<?php

use App\Admin\Clusters\Settings\Pages\ManageSearchSettings;
use App\Admin\Settings\KnowledgeSettings;
use Domain\Knowledge\Embedding\Contracts\Reranker;
use Domain\Knowledge\Embedding\Rerank\NullReranker;
use Livewire\Livewire;

/**
 * The Search page of the admin panel: how agents search their sources.
 * Everyday cards first; the fine-tuning comes last, under Advanced, closed.
 */
beforeEach(function (): void {
    $this->admin = adminUser();
});

it('shows the everyday cards first, and the fine-tuning under Advanced', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->get(ManageSearchSettings::getUrl())
        ->assertOk()
        ->assertSeeInOrder([
            'Finding answers', 'Hybrid search on · 5 results per question',
            'Follow-up questions', 'Understand follow-up questions on',
            'Technical options. The defaults suit most installs.',
            'Fine-tuning', 'Balanced · reranker, minimum 0.3',
        ])
        ->assertDontSee(['File types', 'Website import']);
});

it('uses the basic similarity check while no reranker is set up', function (): void {
    app()->instance(Reranker::class, new NullReranker);
    config(['chatvia.ai.models.embedding' => 'qwen']);

    $this->actingAs($this->admin, 'admin')
        ->get(ManageSearchSettings::getUrl())
        ->assertOk()
        ->assertSee('Balanced · basic check, minimum 0.35');

    Livewire::actingAs($this->admin, 'admin')
        ->test(ManageSearchSettings::class)
        ->assertFormFieldEnabled('relevance_check')
        ->assertFormFieldDisabled('relevance_threshold')
        ->assertFormFieldEnabled('similarity_threshold');
});

it('says when there is nothing to check with', function (): void {
    app()->instance(Reranker::class, new NullReranker);
    config(['chatvia.ai.models.embedding' => 'openai']);

    $this->actingAs($this->admin, 'admin')
        ->get(ManageSearchSettings::getUrl())
        ->assertSee('Balanced · no check measured');
});

it('saves the search options, and they reach the search', function (): void {
    Livewire::actingAs($this->admin, 'admin')
        ->test(ManageSearchSettings::class)
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

it('leaves the Knowledge options alone when it saves', function (): void {
    $settings = app(KnowledgeSettings::class);
    $settings->firecrawl_rate_per_minute = 30;
    $settings->save();

    Livewire::actingAs($this->admin, 'admin')
        ->test(ManageSearchSettings::class)
        ->fillForm(['max_chunks' => 7])
        ->call('save')
        ->assertHasNoFormErrors();

    app()->forgetScopedInstances();

    expect(app(KnowledgeSettings::class))
        ->max_chunks->toBe(7)
        ->firecrawl_rate_per_minute->toBe(30);
});
