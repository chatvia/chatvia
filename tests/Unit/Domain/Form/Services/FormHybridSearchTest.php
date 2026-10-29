<?php

use Domain\Agent\Models\Agent;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Services\FormHybridSearch;

/**
 * Exercises the real two-channel RRF query (vector + keyword) against Postgres,
 * scoped to the agent, plus the cap / fallback policy in forPrompt(). Forms are embedded
 * inline by the observer (sync queue + FakeEmbeddingsProvider) on create, so the
 * dense + sparse columns are populated without touching the network.
 */
function fhsForm(Agent $agent, string $name, string $slug, ?string $description = null): FormDefinition
{
    return FormDefinition::factory()->forAgent($agent)->create([
        'name' => $name,
        'slug' => $slug,
        'agent_instructions' => $description,
        'is_active' => true,
    ]);
}

it('caps an oversized catalog at PROMPT_LIMIT and keeps the agent\'s form matching the visitor intent', function (): void {
    $agent = Agent::factory()->create();
    for ($i = 1; $i <= FormHybridSearch::PROMPT_LIMIT + 2; $i++) {
        fhsForm($agent, "General topic {$i}", "general-topic-{$i}", "Background information number {$i}");
    }
    fhsForm($agent, 'Refund request', 'refund-request', 'Show this when the visitor wants a refund or their money back.');
    fhsForm(Agent::factory()->create(), 'Refund request', 'other-refund', 'Show this when the visitor wants a refund or their money back.');

    $forms = app(FormHybridSearch::class)->forPrompt($agent, 'I would like a refund please');

    expect($forms)->toHaveCount(FormHybridSearch::PROMPT_LIMIT)
        ->and($forms->pluck('slug'))->toContain('refund-request')
        ->and($forms->pluck('slug'))->not->toContain('other-refund');
});

it('falls back to the alphabetical slice when an oversized catalog has no embeddings', function (): void {
    $agent = Agent::factory()->create();
    for ($i = FormHybridSearch::PROMPT_LIMIT + 3; $i >= 1; $i--) {
        $n = sprintf('%02d', $i);
        fhsForm($agent, "Topic {$n}", "topic-{$n}");
    }
    FormDefinition::query()->forAgent($agent)->update(['embedding' => null, 'search_text' => null]);

    $forms = app(FormHybridSearch::class)->forPrompt($agent, 'anything at all');

    expect($forms->pluck('slug')->all())->toBe([
        'topic-01', 'topic-02', 'topic-03', 'topic-04', 'topic-05', 'topic-06', 'topic-07', 'topic-08',
    ]);
});
