<?php

use Domain\Agent\Models\Agent;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Services\FormHybridSearch;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;

/**
 * Exercises the real two-channel RRF query (vchordrq + BM25) against Postgres,
 * plus the small-set / cap / fallback policy in forPrompt(). Forms are embedded
 * inline by the observer (sync queue + FakeEmbeddingsProvider) on create, so the
 * dense + sparse columns are populated without touching the network.
 */
function makeForm(Agent $agent, string $name, string $slug, ?string $description = null): FormDefinition
{
    return FormDefinition::factory()->forAgent($agent)->create([
        'name' => $name,
        'slug' => $slug,
        'agent_instructions' => $description,
        'is_active' => true,
    ]);
}

it('returns an empty collection for an agent with no forms', function (): void {
    $agent = Agent::factory()->create();

    expect(app(FormHybridSearch::class)->forPrompt($agent, 'anything'))->toBeEmpty();
});

it('lists every active form wholesale when the catalog fits the prompt budget', function (): void {
    $agent = Agent::factory()->create();
    makeForm($agent, 'Book a demo', 'book-demo');
    makeForm($agent, 'Get a quote', 'get-quote');
    makeForm($agent, 'Contact us', 'contact-us');

    $forms = app(FormHybridSearch::class)->forPrompt($agent, 'hello there');

    expect($forms->pluck('slug')->all())
        ->toEqualCanonicalizing(['book-demo', 'get-quote', 'contact-us']);
});

it('omits inactive forms from the wholesale list', function (): void {
    $agent = Agent::factory()->create();
    makeForm($agent, 'Live form', 'live-form');
    FormDefinition::factory()->forAgent($agent)->create([
        'name' => 'Archived form', 'slug' => 'archived-form', 'is_active' => false,
    ]);

    expect(app(FormHybridSearch::class)->forPrompt($agent, 'hello')->pluck('slug')->all())
        ->toBe(['live-form']);
});

it('caps an oversized catalog at PROMPT_LIMIT and keeps the form matching the visitor intent', function (): void {
    $agent = Agent::factory()->create();

    // More forms than the prompt budget forces the hybrid-retrieval path.
    for ($i = 1; $i <= FormHybridSearch::PROMPT_LIMIT + 2; $i++) {
        makeForm($agent, "General topic {$i}", "general-topic-{$i}", "Background information number {$i}");
    }
    // One form whose text lexically matches the message — the BM25 channel must
    // float it into the capped set.
    makeForm($agent, 'Refund request', 'refund-request', 'Show this when the visitor wants a refund or their money back.');

    $forms = app(FormHybridSearch::class)->forPrompt($agent, 'I would like a refund please');

    expect($forms)->toHaveCount(FormHybridSearch::PROMPT_LIMIT)
        ->and($forms->pluck('slug'))->toContain('refund-request');
});

it('falls back to the alphabetical slice when an oversized catalog has no embeddings', function (): void {
    $agent = Agent::factory()->create();
    for ($i = 1; $i <= FormHybridSearch::PROMPT_LIMIT + 3; $i++) {
        makeForm($agent, "Topic {$i}", "topic-{$i}");
    }
    // Wipe the embeddings the create-observer populated (mass update skips events)
    // so the hybrid channels return nothing and forPrompt must degrade.
    FormDefinition::query()->forAgent($agent)->update(['embedding' => null, 'embedding_bm25' => null]);

    $forms = app(FormHybridSearch::class)->forPrompt($agent, 'anything at all');

    expect($forms)->toHaveCount(FormHybridSearch::PROMPT_LIMIT);
});

it('search() returns an empty collection when the query embedder fails', function (): void {
    $agent = Agent::factory()->create();
    makeForm($agent, 'Refund request', 'refund-request', 'Issue a refund.');

    // Re-bind the provider to throw on the query-side embed (a flaky embedder must
    // not break the chat turn — search() swallows it and returns empty).
    $this->mock(EmbeddingsProviderInterface::class)
        ->shouldReceive('embedQuery')->andThrow(new RuntimeException('embedder down'));

    expect(app(FormHybridSearch::class)->search($agent, 'refund'))->toBeEmpty();
});

it('search() returns forms in RRF score order, scoped to the agent', function (): void {
    $agent = Agent::factory()->create();
    $other = Agent::factory()->create();
    makeForm($agent, 'Refund request', 'refund-request', 'Issue a refund to the customer.');
    makeForm($other, 'Refund request', 'other-refund', 'Issue a refund to the customer.');

    $forms = app(FormHybridSearch::class)->search($agent, 'refund', limit: 5);

    expect($forms->pluck('slug')->all())->toBe(['refund-request']);
});
