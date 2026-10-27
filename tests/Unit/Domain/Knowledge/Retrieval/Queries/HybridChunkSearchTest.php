<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProvider;
use Domain\Knowledge\Embedding\Contracts\Reranker;
use Domain\Knowledge\Embedding\Rerank\NullReranker;
use Domain\Knowledge\Embedding\Services\EmbeddingStorage;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Retrieval\Actions\ConfigureVectorIndexesAction;
use Domain\Knowledge\Retrieval\Data\RetrievedChunkData;
use Domain\Knowledge\Retrieval\Enums\ChunkSearchScope;
use Domain\Knowledge\Retrieval\Queries\HybridChunkSearch;
use Domain\Knowledge\Retrieval\Services\KnowledgeBase;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * HybridChunkSearch, run through KnowledgeBase::search(). The two ways a
 * search reaches an agent's chunks: exactly (few chunks: every one scored, no
 * index, nothing of another workspace read) and through the indexes (many
 * chunks). Vectors are placed by hand so the true distances are known: the
 * agent's chunks sit at falling similarity to the query, while another
 * workspace's chunks sit right on it and must never come back. The keyword
 * channels run only with hybrid search on, the indexed path ranks the same
 * over binary and full indexes, and the HNSW accuracy settings reach the
 * database as numbers.
 */

/** A unit vector at similarity `$similarity` to the query (axis 0), leaning on axis `$axis`. */
function hcsVector(float $similarity, int $axis): array
{
    $vector = array_fill(0, app(EmbeddingStorage::class)->dimensions(), 0.0);
    $vector[0] = $similarity;
    $vector[$axis] = sqrt(1 - $similarity ** 2);

    return $vector;
}

function hcsChunk(SourceFile $source, int $index, array $vector, string $text): int
{
    return Chunk::factory()->forSource($source)->withVector($vector)->create([
        'chunk_index' => $index,
        'chunk' => $text,
        'tokens' => 8,
    ])->id;
}

/**
 * An agent whose chunks rank in a known order, next to another workspace
 * whose chunks are closer to the query than any of them.
 *
 * @return array{Agent, list<int>} the agent and its chunk ids, closest first
 */
function hcsAgentAmongNeighbours(): array
{
    $agent = Agent::factory()->create();
    $source = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);
    $agent->files()->attach($source->id);

    $ids = [];
    foreach ([0.9, 0.8, 0.7, 0.6, 0.5, 0.4] as $i => $similarity) {
        $ids[] = hcsChunk($source, $i, hcsVector($similarity, $i + 1), "Mountain trail number {$i}.");
    }

    $neighbour = Agent::factory()->create();
    $theirs = AgentFileFactory::new()->create(['workspace_id' => $neighbour->workspace_id]);
    $neighbour->files()->attach($theirs->id);

    for ($i = 0; $i < 30; $i++) {
        hcsChunk($theirs, $i, hcsVector(1.0, 1), "Mountain trail number {$i}.");
    }

    return [$agent, $ids];
}

/**
 * The HNSW settings of a transaction after configureAccuracy() set them for
 * $mode: [hnsw.ef_search, hnsw.iterative_scan]. They are interpolated into
 * `SET LOCAL` (SET takes no bind parameters), so a config string must reach
 * the database as a number.
 *
 * @param  array<string, mixed>  $search
 * @return array{int, string}
 */
function hcsAccuracy(string $mode, array $search): array
{
    return DB::transaction(function () use ($mode, $search): array {
        app(HybridChunkSearch::class)->configureAccuracy($mode, ['search' => $search]);

        return [
            (int) DB::selectOne('SHOW hnsw.ef_search')->{'hnsw.ef_search'},
            (string) DB::selectOne('SHOW hnsw.iterative_scan')->{'hnsw.iterative_scan'},
        ];
    });
}

/** Builds the chunk HNSW indexes as `$kind` (rolled back with the test transaction). */
function hcsIndexes(string $kind): void
{
    config(['chatvia.embeddings.index' => $kind]);

    app(ConfigureVectorIndexesAction::class)->execute();
}

beforeEach(function (): void {
    $query = hcsVector(1.0, 1);

    app()->instance(EmbeddingsProvider::class, Mockery::mock(EmbeddingsProvider::class, [
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
    [$agent] = hcsAgentAmongNeighbours();
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

it('ranks a small agent\'s chunks by true distance and never returns another workspace\'s', function (string $path, string $index): void {
    config(['chatvia.vector.search.exact_max_chunks' => $path === 'exact' ? 10_000 : 2]);
    hcsIndexes($index);
    [$agent, $ids] = hcsAgentAmongNeighbours();

    // Which path a limit picks is the scope() test's.
    $results = app(KnowledgeBase::class)->search($agent, 'mountain trail');

    expect(array_map(fn (RetrievedChunkData $chunk): int => $chunk->id, $results))->toBe($ids)
        // Each result carries the day its text was added, for the newer-wins rule.
        ->and($results[0]->added_on)->toBe(now()->toDateString());
})->with(['exact', 'indexed'])->with(['binary', 'full']);

it('walks the binary index on the indexed path and re-scores by the stored vectors', function (): void {
    config(['chatvia.vector.search.exact_max_chunks' => 0]);
    hcsIndexes('binary');
    [$agent] = hcsAgentAmongNeighbours();

    $captured = null;
    DB::listen(function (QueryExecuted $query) use (&$captured): void {
        if (Str::contains($query->sql, 'vector_ranked AS (')) {
            $captured = [$query->sql, $query->bindings];
        }
    });

    app(KnowledgeBase::class)->search($agent, 'mountain trail');

    // A table this small is cheaper to scan; with scans off the planner can
    // still only pick the index when the ORDER BY repeats its expression.
    $plan = DB::transaction(function () use ($captured): string {
        DB::statement('SET LOCAL enable_seqscan = off');

        return implode("\n", array_column(
            array_map(fn (object $row): array => (array) $row, DB::select('EXPLAIN '.$captured[0], $captured[1])),
            'QUERY PLAN',
        ));
    });

    expect($captured[0])->toContain('<~> binary_quantize(')
        ->and($plan)->toContain('Index Scan using chunks_embedding_hnsw')
        ->and($plan)->toContain('Index Scan using chunks_questions_embedding_hnsw');
});

it('ranks only chunks that share a term with the query on the keyword channel', function (): void {
    config(['chatvia.vector.search.hybrid' => true]);

    $agent = Agent::factory()->create();
    $source = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);
    $agent->files()->attach($source->id);

    $refund = hcsChunk($source, 0, hcsVector(0.5, 1), 'Refund policy: refunds are paid within five days.');
    hcsChunk($source, 1, hcsVector(0.5, 2), 'Chocolate cake with cherries.');
    hcsChunk($source, 2, hcsVector(0.5, 3), 'Mountain hiking trails near the lake.');

    $results = collect(app(KnowledgeBase::class)->search($agent, 'refund'))->keyBy('id');

    expect($results)->toHaveCount(3)
        ->and($results[$refund]->search_type)->toContain('bm25')
        ->and($results->except([$refund])->pluck('search_type')->all())->each->not->toContain('bm25');
});

it('searches by meaning only when hybrid search is turned off', function (bool $hybrid, bool $keywordChannel): void {
    config(['chatvia.vector.search.hybrid' => $hybrid]);

    $agent = Agent::factory()->create();
    $source = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);
    $agent->files()->attach($source->id);
    $refund = hcsChunk($source, 0, hcsVector(0.5, 1), 'Refund policy: refunds are paid within five days.');

    $results = app(KnowledgeBase::class)->search($agent, 'refund');

    expect(array_map(fn (RetrievedChunkData $chunk): int => $chunk->id, $results))->toBe([$refund])
        ->and(Str::contains((string) $results[0]->search_type, 'bm25'))->toBe($keywordChannel);
})->with([
    'hybrid: the keyword channels find it too' => [true, true],
    'meaning only: the vector channels alone find it' => [false, false],
]);

it('never scans a vector or keyword index on the exact path', function (): void {
    config(['chatvia.vector.search.hybrid' => true]);
    [$agent] = hcsAgentAmongNeighbours();

    $captured = null;
    DB::listen(function (QueryExecuted $query) use (&$captured): void {
        if (Str::contains($query->sql, 'scope AS MATERIALIZED')) {
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

it('casts ef_search to a number and turns on iterative scans', function (): void {
    expect(hcsAccuracy('default', ['ef_search' => ['default' => '150 ; SELECT 1']]))->toBe([150, 'relaxed_order']);
});

it('uses the ef_search of the requested mode and falls back to the default mode', function (string $mode, int $efSearch): void {
    expect(hcsAccuracy($mode, ['ef_search' => ['default' => 100, 'high_accuracy' => 300]]))->toBe([$efSearch, 'relaxed_order']);
})->with([
    'the requested mode' => ['high_accuracy', 300],
    'an unknown mode' => ['unknown_mode', 100],
]);

it('keeps ef_search inside the range the index accepts', function (int $configured, int $efSearch): void {
    expect(hcsAccuracy('default', ['ef_search' => ['default' => $configured]]))->toBe([$efSearch, 'relaxed_order']);
})->with([
    'too low' => [0, 1],
    'too high' => [100000, 1000],
]);
