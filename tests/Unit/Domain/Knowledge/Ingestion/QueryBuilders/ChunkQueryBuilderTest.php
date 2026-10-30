<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Models\Chunk;

/**
 * Agent scoping of knowledge chunks: a chunk counts for an agent only when its
 * source is attached to the agent and the chunk sits in the agent's workspace.
 */
it('scopes chunks to the agent\'s sources inside the agent\'s workspace', function (): void {
    $agent = Agent::factory()->create();
    $file = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);
    $agent->files()->attach($file->id);
    $unattached = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);
    $otherWorkspace = Agent::factory()->create()->workspace_id;

    $own = Chunk::factory()->forSource($file)->create(['chunk' => 'Own chunk.', 'questions' => 'What is ours?']);
    Chunk::factory()->forSource($file)->create(['workspace_id' => $otherWorkspace, 'chunk' => 'Same source, other workspace.', 'questions' => 'What is theirs?']);
    Chunk::factory()->forSource($unattached)->create(['chunk' => 'Source the agent does not use.']);

    expect(Chunk::query()->hasContentForAgent($agent))->toBeTrue()
        ->and(Chunk::query()->contentSampleForAgent($agent))->toBe(['Own chunk.'])
        ->and(Chunk::query()->randomContentForAgent($agent, 5))->toBe([['chunk_id' => $own->id, 'text' => 'Own chunk.', 'source_type' => 'source_file']])
        ->and(Chunk::query()->questionsForAgent($agent))->toBe(['What is ours?'])
        ->and(collect(Chunk::query()->questionCandidatesForAgent($agent))->pluck('chunk_id')->all())->toBe([$own->id]);
});

it('finds nothing for an agent with no chunks in a workspace full of them', function (): void {
    $busy = Agent::factory()->create();
    $file = AgentFileFactory::new()->create(['workspace_id' => $busy->workspace_id]);
    $busy->files()->attach($file->id);
    Chunk::factory()->forSource($file)->count(3)->create(['questions' => 'Is this yours?']);

    $quiet = Agent::factory()->create(['workspace_id' => $busy->workspace_id, 'team_id' => $busy->team_id]);
    $untrained = AgentFileFactory::new()->create(['workspace_id' => $busy->workspace_id]);
    $quiet->files()->attach($untrained->id);

    expect(Chunk::query()->hasContentForAgent($quiet))->toBeFalse()
        ->and(Chunk::query()->contentSampleForAgent($quiet))->toBe([])
        ->and(Chunk::query()->randomContentForAgent($quiet, 5))->toBe([])
        ->and(Chunk::query()->questionsForAgent($quiet))->toBe([])
        ->and(Chunk::query()->questionCandidatesForAgent($quiet))->toBe([])
        ->and(Chunk::query()->hasContentForAgent($busy))->toBeTrue();
});

it('finds the page chunks no page links to, never the summary chunk', function (): void {
    $agent = Agent::factory()->create();
    $file = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);
    $page = Chunk::factory()->forSource($file)->create(['chunk' => 'Unlinked page chunk.']);
    Chunk::factory()->forSource($file)->summary()->create(['chunk' => 'Summary chunk.']);

    expect(Chunk::query()->ofSource($file)->unreferenced()->pluck('id')->all())->toBe([$page->id])
        ->and(Chunk::query()->ofSource($file)->count())->toBe(2);
});
