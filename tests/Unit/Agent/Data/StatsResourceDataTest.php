<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Data\Resources\Stats\StatsResourceData;
use Domain\Agent\Models\Agent;

/**
 * The "top knowledge" list reports each file's document type. The query must
 * select `document_classification`, otherwise every row reads as "unknown".
 *
 * Needs the seeded test DB: AgentFileFactory reuses the seeded demo user.
 */
it('reports the document classification of the most accessed files', function (): void {
    $agent = Agent::factory()->create();

    $classified = AgentFileFactory::new()->create(['filename' => 'pricing.pdf', 'document_classification' => 'pricing_sheet']);
    $classified->agents()->attach($agent->id, ['retrieval_count' => 7]);

    $unclassified = AgentFileFactory::new()->create(['filename' => 'notes.txt', 'document_classification' => null]);
    $unclassified->agents()->attach($agent->id, ['retrieval_count' => 2]);

    $top = StatsResourceData::mostAccessedFiles($agent);

    expect($top)->toBe([
        ['name' => 'pricing.pdf', 'type' => 'pricing_sheet', 'accessed' => 7],
        ['name' => 'notes.txt', 'type' => 'unknown', 'accessed' => 2],
    ]);
});

it('no longer exposes the always-zero total_members field', function (): void {
    expect(array_map(
        static fn (ReflectionParameter $p): string => $p->getName(),
        (new ReflectionMethod(StatsResourceData::class, '__construct'))->getParameters(),
    ))->not->toContain('total_members');
});

it('builds the full analytics payload for an agent', function (): void {
    $agent = Agent::factory()->create();

    $payload = StatsResourceData::fromAgent($agent)->toArray();

    expect($payload)->toHaveKeys(['total_messages', 'top_knowledge', 'feedback'])
        ->and($payload)->not->toHaveKey('total_members');
});
