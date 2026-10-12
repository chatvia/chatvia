<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Retrieval\Services\Bm25Tokenizer;
use Domain\Knowledge\Source\File\Models\SourceFile;

/**
 * Agent scoping of knowledge chunks: a chunk counts for an agent only when its
 * source is attached to the agent and the chunk sits in the agent's workspace.
 */
function cqbChunk(SourceFile $file, int $workspaceId, string $text, ?string $questions = null): Chunk
{
    return Chunk::query()->create([
        'workspace_id' => $workspaceId,
        'chunkable_type' => $file->getMorphClass(),
        'chunkable_id' => $file->id,
        'chunk_index' => 1,
        'chunk' => $text,
        'questions' => $questions,
        'tokens' => 3,
        'embedding' => '['.implode(',', array_fill(0, 1024, 0.1)).']',
        'embedding_bm25' => Bm25Tokenizer::expression($text),
        'checksum' => md5($text.uniqid()),
        'cost_in_micro_cents' => 0,
    ]);
}

it('scopes chunks to the agent\'s sources inside the agent\'s workspace', function (): void {
    $agent = Agent::factory()->create();
    $file = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);
    $agent->files()->attach($file->id);
    $unattached = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);
    $otherWorkspace = Agent::factory()->create()->workspace_id;

    $own = cqbChunk($file, $agent->workspace_id, 'Own chunk.', 'What is ours?');
    $foreign = cqbChunk($file, $otherWorkspace, 'Same source, other workspace.', 'What is theirs?');
    cqbChunk($unattached, $agent->workspace_id, 'Source the agent does not use.');

    expect(Chunk::query()->forAgent($agent)->pluck('id')->all())->toBe([$own->id])
        ->and(Chunk::query()->hasContentForAgent($agent))->toBeTrue()
        ->and(Chunk::query()->contentSampleForAgent($agent))->toBe(['Own chunk.'])
        ->and(Chunk::query()->questionsForAgent($agent))->toBe(['What is ours?'])
        ->and(collect(Chunk::query()->questionCandidatesForAgent($agent))->pluck('chunk_id')->all())->toBe([$own->id])
        ->and($foreign->exists)->toBeTrue();
});

it('finds no content for an agent without sources', function (): void {
    $agent = Agent::factory()->create();

    expect(Chunk::query()->hasContentForAgent($agent))->toBeFalse();
});

it('finds the page chunks no page links to, never the summary chunk', function (): void {
    $agent = Agent::factory()->create();
    $file = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);
    $page = cqbChunk($file, $agent->workspace_id, 'Unlinked page chunk.');
    $summary = cqbChunk($file, $agent->workspace_id, 'Summary chunk.');
    $summary->update(['chunk_index' => 0]);

    expect(Chunk::query()->ofSource($file)->unreferenced()->pluck('id')->all())->toBe([$page->id])
        ->and(Chunk::query()->ofSource($file)->count())->toBe(2);
});
