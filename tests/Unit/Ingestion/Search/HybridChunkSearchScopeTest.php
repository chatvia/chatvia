<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Domain\Knowledge\Embedding\Contracts\Reranker;
use Domain\Knowledge\Embedding\Rerank\NullReranker;
use Domain\Knowledge\Retrieval\Data\RetrievedChunkData;
use Domain\Knowledge\Retrieval\Enums\ChunkSearchScope;
use Domain\Knowledge\Retrieval\Queries\HybridChunkSearch;
use Domain\Knowledge\Retrieval\Services\Bm25Tokenizer;
use Domain\Knowledge\Retrieval\Services\KnowledgeBase;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Support\Facades\DB;

/**
 * The two ways a knowledge search reaches an agent's chunks: exactly (few
 * chunks: every one scored, no index, nothing of another workspace read) and
 * through the indexes (many chunks). Vectors are placed by hand so the true
 * distances are known: the agent's chunks sit at falling similarity to the
 * query, while another workspace's chunks sit right on it and must never come
 * back.
 */

/** A unit vector at similarity `$similarity` to the query (axis 0), leaning on axis `$axis`. */
function scopeTestVector(float $similarity, int $axis): array
{
    $vector = array_fill(0, 1024, 0.0);
    $vector[0] = $similarity;
    $vector[$axis] = sqrt(1 - $similarity ** 2);

    return $vector;
}

function scopeTestChunk(SourceFile $source, int $index, array $vector, string $text): int
{
    return DB::table('chunks')->insertGetId([
        'workspace_id' => $source->workspace_id,
        'chunkable_type' => $source->getMorphClass(),
        'chunkable_id' => $source->id,
        'chunk_index' => $index,
        'chunk' => $text,
        'tokens' => 8,
        'embedding' => '['.implode(',', $vector).']',
        'embedding_bm25' => Bm25Tokenizer::expression($text),
        'checksum' => md5($source->id.':'.$index),
    ]);
}

/**
 * An agent whose chunks rank in a known order, next to another workspace
 * whose chunks are closer to the query than any of them.
 *
 * @return array{Agent, list<int>} the agent and its chunk ids, closest first
 */
function scopeTestAgentAmongNeighbours(): array
{
    $agent = Agent::factory()->create();
    $source = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);
    $agent->files()->attach($source->id);

    $ids = [];
    foreach ([0.9, 0.8, 0.7, 0.6, 0.5, 0.4] as $i => $similarity) {
        $ids[] = scopeTestChunk($source, $i, scopeTestVector($similarity, $i + 1), "Mountain trail number {$i}.");
    }

    $neighbour = Agent::factory()->create();
    $theirs = AgentFileFactory::new()->create(['workspace_id' => $neighbour->workspace_id]);
    $neighbour->files()->attach($theirs->id);

    for ($i = 0; $i < 30; $i++) {
        scopeTestChunk($theirs, $i, scopeTestVector(1.0, 1), "Mountain trail number {$i}.");
    }

    return [$agent, $ids];
}

beforeEach(function (): void {
    $query = scopeTestVector(1.0, 1);

    app()->instance(EmbeddingsProviderInterface::class, Mockery::mock(EmbeddingsProviderInterface::class, [
        'embedQuery' => $query,
        'embed' => $query,
        'getDimensions' => 1024,
        'getModelName' => 'scope-test',
    ]));
    app()->instance(Reranker::class, new NullReranker);

    config([
        'chatvia.vector.search.relevance_check' => false,
        'chatvia.vector.search.max_chunks' => 6,
        // Meaning only, so the order is the vector order.
        'chatvia.vector.search.hybrid' => false,
    ]);
});

it('picks the path from a count of the agent\'s chunks, bounded by the limit', function (): void {
    [$agent] = scopeTestAgentAmongNeighbours();
    $search = app(HybridChunkSearch::class);

    expect($search->scope($agent, 6))->toBe(ChunkSearchScope::Exact)
        ->and($search->scope($agent, 10_000))->toBe(ChunkSearchScope::Exact)
        ->and($search->scope($agent, 5))->toBe(ChunkSearchScope::Indexed)
        ->and($search->scope($agent, 0))->toBe(ChunkSearchScope::Indexed);

    $withoutChunks = Agent::factory()->create();
    $withoutChunks->files()->attach(AgentFileFactory::new()->create(['workspace_id' => $withoutChunks->workspace_id])->id);

    expect($search->scope($withoutChunks, 10_000))->toBe(ChunkSearchScope::None)
        ->and($search->scope(Agent::factory()->create(), 10_000))->toBe(ChunkSearchScope::None);
});

it('skips the query embedding when the agent reaches no chunks', function (): void {
    $agent = Agent::factory()->create();
    $agent->files()->attach(AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id])->id);

    $embeddings = Mockery::mock(EmbeddingsProviderInterface::class);
    $embeddings->shouldNotReceive('embedQuery');
    app()->instance(EmbeddingsProviderInterface::class, $embeddings);

    expect(app(KnowledgeBase::class)->search($agent, 'anything'))->toBe([]);
});

it('ranks a small agent\'s chunks by true distance and never returns another workspace\'s', function (string $path): void {
    config(['chatvia.vector.search.exact_max_chunks' => $path === 'exact' ? 10_000 : 2]);
    [$agent, $ids] = scopeTestAgentAmongNeighbours();

    $statements = [];
    DB::listen(function ($query) use (&$statements): void {
        $statements[] = $query->sql;
    });

    $results = app(KnowledgeBase::class)->search($agent, 'mountain trail');

    expect(array_map(fn (RetrievedChunkData $chunk): int => $chunk->id, $results))->toBe($ids)
        ->and(implode("\n", $statements))->toContain($path === 'exact' ? 'scope AS MATERIALIZED' : 'workspace_id + 0');
})->with(['exact', 'indexed']);

it('ranks only chunks that share a term with the query on the keyword channel', function (): void {
    config(['chatvia.vector.search.hybrid' => true]);

    $agent = Agent::factory()->create();
    $source = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);
    $agent->files()->attach($source->id);

    $refund = scopeTestChunk($source, 0, scopeTestVector(0.5, 1), 'Refund policy: refunds are paid within five days.');
    scopeTestChunk($source, 1, scopeTestVector(0.5, 2), 'Chocolate cake with cherries.');
    scopeTestChunk($source, 2, scopeTestVector(0.5, 3), 'Mountain hiking trails near the lake.');

    $results = collect(app(KnowledgeBase::class)->search($agent, 'refund'))->keyBy('id');

    expect($results)->toHaveCount(3)
        ->and($results[$refund]->search_type)->toContain('bm25')
        ->and($results->except([$refund])->pluck('search_type')->all())->each->not->toContain('bm25');
});

it('never scans a vector or keyword index on the exact path', function (): void {
    config(['chatvia.vector.search.hybrid' => true]);
    [$agent] = scopeTestAgentAmongNeighbours();

    $captured = null;
    DB::listen(function ($query) use (&$captured): void {
        if (str_contains($query->sql, 'scope AS MATERIALIZED')) {
            $captured = [$query->sql, $query->bindings];
        }
    });

    app(KnowledgeBase::class)->search($agent, 'mountain trail');

    $plan = implode("\n", array_column(
        array_map(fn (object $row): array => (array) $row, DB::select('EXPLAIN '.$captured[0], $captured[1])),
        'QUERY PLAN',
    ));

    expect($plan)->not->toContain('Index Scan using chunks_embedding')
        ->and($plan)->not->toContain('Index Scan using chunks_questions_embedding')
        ->and($plan)->toContain('agent_sources');
});
