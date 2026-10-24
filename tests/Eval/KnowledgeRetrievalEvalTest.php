<?php

declare(strict_types=1);

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Domain\Knowledge\Embedding\Contracts\Reranker;
use Domain\Knowledge\Embedding\Services\EmbeddingStorage;
use Domain\Knowledge\Embedding\Services\RelevanceThresholds;
use Domain\Knowledge\Ingestion\Contracts\TextSplitter;
use Domain\Knowledge\Ingestion\Data\Chunking\ChunkingConfigData;
use Domain\Knowledge\Ingestion\Enums\ChunkEnhancement;
use Domain\Knowledge\Ingestion\Enums\ContentKind;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Ingestion\Services\Chunking\BuiltinTextSplitter;
use Domain\Knowledge\Retrieval\Data\RetrievalSettingsData;
use Domain\Knowledge\Retrieval\Data\RetrievedChunkData;
use Domain\Knowledge\Retrieval\Queries\HybridChunkSearch;
use Domain\Knowledge\Retrieval\Services\KnowledgeBase;
use Domain\Knowledge\Retrieval\Services\LexicalSearch;
use Domain\Knowledge\Source\Qa\Actions\CreateAgentQaAction;
use Domain\Knowledge\Source\Qa\Actions\EmbedQaQuestionsAction;
use Domain\Knowledge\Source\Qa\Data\QaEntryData;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Llm\Operations\Query\QueryRewriter;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

/**
 * OFFLINE EVAL — not a pass/fail gate. It measures knowledge retrieval against
 * a labelled set (fixtures/knowledge/cases.php over the three chunking samples
 * and an Arabic page; format in README.md) and prints a scoreboard:
 *
 *  - before the relevance check (the hybrid search's ranking): hit@1/3/5, MRR;
 *  - after it (what the model gets, KnowledgeBase::search): how many questions
 *    got their passage, and how many uncovered questions got nothing;
 *  - the check's scores for the right passage and for uncovered questions, with
 *    a threshold sweep — this is how the default thresholds are chosen;
 *  - for multi-hop and global questions (several facts each): how many facts
 *    rank high and reach the model, and how many questions get every fact.
 *
 * It runs the REAL embeddings provider and reranker (the test fakes would make
 * every number noise), so it needs keys and network, and skips itself without
 * them:
 *
 *     SCALEWAYAI_API_KEY=… vendor/bin/pest --testsuite=Eval --filter=knowledge
 *
 * Knobs (env, one run): VECTOR_SEARCH_RELEVANCE_THRESHOLD, VECTOR_SEARCH_MAX_CHUNKS,
 * EVAL_ENRICH=1 (enrich chunks with the Smart model first; needs a chat model),
 * EVAL_ENRICH_WITHOUT=summary|questions (then take that part back out),
 * EVAL_CHUNK_SIZE and EVAL_CHUNK_MINIMUM (the chunk size and the smallest
 * chunk, in the configured unit), EVAL_SIMILARITY_THRESHOLD (the bar without
 * a reranker).
 */
beforeEach(function (): void {
    // The eval calls the real embeddings and reranker, so it runs only with a
    // key on the command line, and skips before any request without one.
    try {
        evalUseRealModels();
    } catch (Throwable $e) {
        $this->markTestSkipped($e->getMessage());
    }

    // The real splitter, as production splits these documents.
    app()->instance(TextSplitter::class, new BuiltinTextSplitter);

    foreach (['EVAL_CHUNK_SIZE' => 'size', 'EVAL_CHUNK_MINIMUM' => 'minimum'] as $variable => $setting) {
        if (filled($value = env($variable))) {
            config(["chatvia.ai.chunking.{$setting}" => (int) $value]);
        }
    }

    // The similarity bar without a reranker. Only Qwen3 on Scaleway ships a
    // measured one (0.35), so the same model through another provider needs
    // it given to match a default install.
    if (filled($threshold = env('EVAL_SIMILARITY_THRESHOLD'))) {
        config(['chatvia.vector.search.similarity_threshold' => (float) $threshold]);
    }
});

/** How the eval documents split with this run's settings: the count and the sizes of the chunks. */
function retrievalEvalChunkLine(): string
{
    $sizes = [];

    foreach (retrievalEvalDocuments() as $path => $kind) {
        $config = ChunkingConfigData::forKind($kind);

        foreach (app(TextSplitter::class)->split((string) file_get_contents($path), $kind) as $chunk) {
            $sizes[] = $config->measure()->length($chunk);
        }
    }

    sort($sizes);
    $config = ChunkingConfigData::forKind(ContentKind::Text);

    return sprintf('chunks: size %d, smallest %d %s · %d chunks, min %d · median %d · max %d · under the smallest %d',
        $config->size, $config->minimum, $config->unit->suffix(), count($sizes), $sizes[0], $sizes[intdiv(count($sizes), 2)], end($sizes),
        count(array_filter($sizes, static fn (int $size): bool => $size < $config->minimum)));
}

/** The eval's documents: path => how it is split. */
function retrievalEvalDocuments(): array
{
    return [
        resource_path('chunking/markdown.md') => ContentKind::Markdown,
        resource_path('chunking/text.txt') => ContentKind::Text,
        resource_path('chunking/transcript.txt') => ContentKind::Transcript,
        __DIR__.'/fixtures/knowledge/arabic.md' => ContentKind::Markdown,
    ];
}

/** An agent that knows the eval documents, split and embedded for real. */
function retrievalEvalAgent(): Agent
{
    $agent = Agent::factory()->create();
    $enhancement = env('EVAL_ENRICH') ? ChunkEnhancement::Text : ChunkEnhancement::Off;

    foreach (retrievalEvalDocuments() as $path => $kind) {
        $text = (string) file_get_contents($path);

        $source = SourceText::factory()->ingested()->attachedTo($agent)->create([
            'excerpt' => Str::substr($text, 0, 200),
            'length' => Str::length($text),
            'checksum' => md5($path.$text),
        ]);

        storeSourceChunks($source, app(TextSplitter::class)->split($text, $kind), $enhancement);
    }

    return $agent;
}

/**
 * Takes one part of the enhancement back out of the agent's chunks, to see
 * what that part does to the ranking (EVAL_ENRICH_WITHOUT): "summary" embeds
 * and indexes each chunk's own text without the summary in front;
 * "questions" drops the generated questions and their embedding, which turns
 * the two question channels off.
 */
function retrievalEvalWithout(Agent $agent, string $part): void
{
    $chunks = Chunk::query()->where('workspace_id', $agent->workspace_id)->get();

    if ($part === 'questions') {
        Chunk::query()->whereKey($chunks->modelKeys())->update(['questions' => null, 'questions_embedding' => null]);

        return;
    }

    $cast = app(EmbeddingStorage::class)->columnType();
    $vectors = app(EmbeddingsProviderInterface::class)->embedBatch($chunks->pluck('chunk')->all());

    foreach ($chunks->values() as $index => $chunk) {
        DB::update("UPDATE chunks SET embedding = ?::{$cast}, search_text = chunk WHERE id = ?", ['['.implode(',', $vectors[$index]).']', $chunk->id]);
    }
}

/** The search question of a case: a follow-up goes through the rewriter, with its history. */
function retrievalEvalQuery(array $case): string
{
    if (! isset($case['history'])) {
        return $case['query'];
    }

    $history = array_map(static fn (string $message): array => ['role' => 'user', 'content' => $message], $case['history']);

    return app(QueryRewriter::class)->rewrite($case['query'], $history)->query;
}

/**
 * The 1-based rank of the first chunk that holds the expected text, or null.
 *
 * @param  list<RetrievedChunkData>  $chunks
 */
function retrievalEvalRank(array $chunks, string $expect): ?int
{
    foreach ($chunks as $index => $chunk) {
        if (mb_stripos($chunk->chunk, $expect) !== false) {
            return $index + 1;
        }
    }

    return null;
}

/**
 * The relevance check's score for each candidate, by index; null when no
 * check is set up.
 *
 * @param  list<RetrievedChunkData>  $candidates
 * @return array<int, float>|null
 */
function retrievalEvalScores(string $query, array $candidates): ?array
{
    $reranker = app(Reranker::class);

    if ($candidates === [] || ! $reranker->isEnabled()) {
        return null;
    }

    $scores = [];

    foreach ($reranker->rerank($query, array_map(static fn (RetrievedChunkData $chunk): string => $chunk->chunk, $candidates), count($candidates)) as $item) {
        $scores[$item->index] = $item->relevanceScore;
    }

    return $scores;
}

it('measures knowledge retrieval quality against the labelled set', function (): void {
    $cases = require __DIR__.'/fixtures/knowledge/cases.php';
    $agent = retrievalEvalAgent();

    if (env('EVAL_ENRICH') && filled($part = env('EVAL_ENRICH_WITHOUT'))) {
        retrievalEvalWithout($agent, $part);
    }

    $settings = RetrievalSettingsData::fromConfig();
    $search = app(HybridChunkSearch::class);
    $embeddings = app(EmbeddingsProviderInterface::class);
    $pool = max($settings->candidateLimit(), 25);

    $rows = [];
    $factRows = [];
    $asked = [];

    foreach ($cases as $case) {
        $query = retrievalEvalQuery($case);
        $vector = '['.implode(',', $embeddings->embedQuery($query)).']';
        $asked[] = ['query' => $query, 'vector' => $vector];
        $scope = $search->scope($agent, $settings->exactMaxChunks);
        $candidates = $search->search($agent, $query, $vector, $pool, $scope, $settings->accuracy);
        $shipped = app(KnowledgeBase::class)->search($agent, $query);

        if (isset($case['kind'])) {
            $factRows[] = retrievalEvalFactsRow($case, $candidates, $shipped);

            continue;
        }

        $scores = retrievalEvalScores($query, $candidates);

        $rank = $case['expect'] !== null ? retrievalEvalRank($candidates, $case['expect']) : null;

        $rows[] = [
            'query' => $case['query'],
            'kind' => $case['expect'] === null ? 'uncovered' : (isset($case['history']) ? 'follow-up' : 'answer'),
            'rank' => $rank,
            'shipped_hit' => $case['expect'] !== null && retrievalEvalRank($shipped, $case['expect']) !== null,
            'shipped_count' => count($shipped),
            // The right passage's score, or the best score of an uncovered question.
            'score' => $scores === null ? null : ($case['expect'] === null
                ? ($scores === [] ? 0.0 : max($scores))
                : ($rank !== null ? ($scores[$rank - 1] ?? null) : null)),
        ];
    }

    retrievalEvalReport($rows, $settings);
    retrievalEvalFactsReport($factRows, $settings);

    if (filled($weights = env('EVAL_CHANNEL_WEIGHTS'))) {
        retrievalEvalWeightsReport($agent, $cases, $asked, $settings, $pool, (string) $weights);
    }

    retrievalEvalQaReport($agent);

    expect(count($rows) + count($factRows))->toBe(count($cases));
})->group('eval');

/**
 * A multi-hop or global case: for each of its facts, the rank of the first
 * candidate that holds it, and whether the model got it.
 *
 * @param  array{query: string, kind: string, expect: list<string|list<string>>}  $case
 * @param  list<RetrievedChunkData>  $candidates
 * @param  list<RetrievedChunkData>  $shipped
 * @return array{query: string, kind: string, facts: list<string|list<string>>, fact_ranks: list<int|null>, fact_shipped: list<bool>}
 */
function retrievalEvalFactsRow(array $case, array $candidates, array $shipped): array
{
    // A fact worded two ways counts at the best rank of either wording.
    $rankOf = static fn (array $chunks, string|array $fact): ?int => collect((array) $fact)
        ->map(static fn (string $text): ?int => retrievalEvalRank($chunks, $text))
        ->filter()
        ->min();

    return [
        'query' => $case['query'],
        'kind' => $case['kind'],
        'facts' => $case['expect'],
        'fact_ranks' => array_map(static fn (string|array $fact): ?int => $rankOf($candidates, $fact), $case['expect']),
        'fact_shipped' => array_map(static fn (string|array $fact): bool => $rankOf($shipped, $fact) !== null, $case['expect']),
    ];
}

/**
 * The exact path of HybridChunkSearch with a weight on each channel's vote in
 * the fusion, to measure a ranking change before it goes into the search.
 * The same scope, channels and RRF (k = 60); all weights 1 gives the
 * search's own ranking, and a weight of 0 leaves the channel out.
 *
 * @param  array{vector: float, question: float, bm25: float, question_bm25: float}  $weights
 * @return list<RetrievedChunkData>
 */
function retrievalEvalWeightedSearch(Agent $agent, string $query, string $vector, int $limit, array $weights): array
{
    $lexical = app(LexicalSearch::class);
    $cast = app(EmbeddingStorage::class)->columnType();
    $perChannel = $limit * 3;
    $columns = ['vector' => 'vector_dist', 'question' => 'question_dist', 'bm25' => 'bm25_dist', 'question_bm25' => 'question_bm25_dist'];

    $ranked = collect($columns)->map(static fn (string $column, string $type): string => <<<SQL
    SELECT id, ROW_NUMBER() OVER (ORDER BY dist, id) AS rank, '{$type}' AS search_type
    FROM (SELECT id, {$column} AS dist FROM scope WHERE {$column} IS NOT NULL ORDER BY {$column}, id LIMIT {$perChannel}) t
SQL)->implode("\n    UNION ALL\n");

    $votes = collect($weights)->map(static fn (float $weight, string $type): string => sprintf("WHEN '%s' THEN %F", $type, $weight))->implode(' ');

    $sql = <<<SQL
WITH search AS MATERIALIZED (
    SELECT ?::{$cast} AS vector,
           {$lexical->queryExpression('chunks_search_text_lexical')} AS chunk_terms,
           {$lexical->queryExpression('chunks_questions_lexical')} AS question_terms
),
scope AS MATERIALIZED (
    SELECT c.id,
           c.embedding <-> q.vector AS vector_dist,
           c.questions_embedding <-> q.vector AS question_dist,
           NULLIF({$lexical->score('c.search_text', 'q.chunk_terms')}, 0) AS bm25_dist,
           NULLIF({$lexical->score('c.questions', 'q.question_terms')}, 0) AS question_bm25_dist
    FROM search q
    CROSS JOIN agent_sources s
    JOIN chunks c ON c.chunkable_type = s.sourceable_type AND c.chunkable_id = s.sourceable_id
    WHERE s.agent_id = ? AND c.workspace_id = ?
),
ranked AS (
{$ranked}
),
fused AS (
    SELECT id, STRING_AGG(DISTINCT search_type, '+') AS search_type, SUM((CASE search_type {$votes} END) / (60 + rank)) AS score
    FROM ranked
    GROUP BY id
)
SELECT c.id, c.external_id, c.chunk, c.chunk_index, c.chunkable_type, c.chunkable_id, c.metadata, c.tokens,
       f.search_type, f.score,
       GREATEST(1 - (c.embedding <=> q.vector), COALESCE(1 - (c.questions_embedding <=> q.vector), 0)) AS similarity
FROM fused f
JOIN chunks c USING (id)
CROSS JOIN search q
WHERE f.score > 0
ORDER BY f.score DESC, f.id
LIMIT ?
SQL;

    return array_map(RetrievedChunkData::fromRow(...), DB::select($sql, [
        $vector, $lexical->queryText($query), $lexical->queryText($query), $agent->id, (int) $agent->workspace_id, $limit,
    ]));
}

/**
 * The scoreboard again for each set of channel weights in EVAL_CHANNEL_WEIGHTS
 * ("vector,question,bm25,question_bm25" sets split by ";", e.g.
 * "1,1,1,1;1,0.5,1,0.5"), on the same chunks and questions. "Given" is the
 * similarity check (KnowledgeBase::bySimilarity): the candidates at or above
 * the bar, in the ranking's order, at most max_chunks; with a reranker on,
 * the reranker would decide instead, so run this with EVAL_RERANKER=none.
 *
 * @param  list<array<string, mixed>>  $cases
 * @param  list<array{query: string, vector: string}>  $asked
 */
function retrievalEvalWeightsReport(Agent $agent, array $cases, array $asked, RetrievalSettingsData $settings, int $pool, string $spec): void
{
    $threshold = RelevanceThresholds::knowledgeSimilarity();
    $pct = static fn (int $part, int $whole): string => $whole === 0 ? '  n/a ' : sprintf('%5.1f%%', 100 * $part / $whole);
    $holds = static fn (array $chunks, string|array $fact): bool => collect((array) $fact)->contains(static fn (string $text): bool => retrievalEvalRank($chunks, $text) !== null);

    $out = ['', sprintf('=== Channel weights (vector, question, keyword, question keyword) · similarity bar %s ===', $threshold ?? 'none')];
    $out[] = 'weights               hit@1   hit@5    MRR   answered  uncovered empty  multi-hop facts top 5  multi-hop all given  global facts given';

    foreach (Str::of($spec)->explode(';')->map(static fn (string $set): string => Str::trim($set))->filter() as $set) {
        $values = Str::of($set)->explode(',')->map(static fn (string $value): float => (float) $value)->all();
        $weights = ['vector' => $values[0] ?? 1.0, 'question' => $values[1] ?? 1.0, 'bm25' => $values[2] ?? 1.0, 'question_bm25' => $values[3] ?? 1.0];

        $answers = ['n' => 0, 'hit1' => 0, 'hit5' => 0, 'rr' => 0.0, 'given' => 0];
        $uncovered = ['n' => 0, 'empty' => 0];
        $multi = ['facts' => 0, 'top5' => 0, 'n' => 0, 'all' => 0];
        $global = ['facts' => 0, 'given' => 0];

        foreach ($cases as $index => $case) {
            $candidates = retrievalEvalWeightedSearch($agent, $asked[$index]['query'], $asked[$index]['vector'], $pool, $weights);
            $given = array_slice(array_values(array_filter($candidates, static fn (RetrievedChunkData $chunk): bool => $threshold === null || ($chunk->similarity ?? 0) >= $threshold)), 0, $settings->maxChunks);

            if (($case['kind'] ?? null) === 'multi-hop') {
                $all = true;

                foreach ($case['expect'] as $fact) {
                    $multi['facts']++;
                    $multi['top5'] += (int) $holds(array_slice($candidates, 0, 5), $fact);
                    $all = $all && $holds($given, $fact);
                }

                $multi['n']++;
                $multi['all'] += (int) $all;
            } elseif (($case['kind'] ?? null) === 'global') {
                foreach ($case['expect'] as $fact) {
                    $global['facts']++;
                    $global['given'] += (int) $holds($given, $fact);
                }
            } elseif ($case['expect'] === null) {
                $uncovered['n']++;
                $uncovered['empty'] += (int) ($given === []);
            } elseif (! isset($case['history'])) {
                $rank = retrievalEvalRank($candidates, $case['expect']);
                $answers['n']++;
                $answers['hit1'] += (int) ($rank === 1);
                $answers['hit5'] += (int) ($rank !== null && $rank <= 5);
                $answers['rr'] += $rank !== null ? 1 / $rank : 0.0;
                $answers['given'] += (int) (retrievalEvalRank($given, $case['expect']) !== null);
            }
        }

        $out[] = sprintf('%-20s %s  %s  %.3f   %s       %s              %s               %s               %s',
            collect($weights)->map(static fn (float $value): string => (string) Number::format($value, maxPrecision: 2))->implode(', '),
            $pct($answers['hit1'], $answers['n']), $pct($answers['hit5'], $answers['n']), $answers['n'] > 0 ? $answers['rr'] / $answers['n'] : 0.0,
            $pct($answers['given'], $answers['n']), $pct($uncovered['empty'], $uncovered['n']),
            $pct($multi['top5'], $multi['facts']), $pct($multi['all'], $multi['n']), $pct($global['given'], $global['facts']));
    }

    fwrite(STDOUT, implode(PHP_EOL, $out).PHP_EOL);
}

/**
 * Multi-hop and global questions: how many of their facts the search ranks
 * high and the model gets, and how many questions get every fact. The
 * baseline a graph-based retriever has to beat.
 *
 * @param  list<array{query: string, kind: string, facts: list<string|list<string>>, fact_ranks: list<int|null>, fact_shipped: list<bool>}>  $rows
 */
function retrievalEvalFactsReport(array $rows, RetrievalSettingsData $settings): void
{
    $pct = static fn (int $part, int $whole): string => $whole === 0 ? '  n/a' : sprintf('%5.1f%%', 100 * $part / $whole);
    $within = static fn (array $row, int $k): int => count(array_filter($row['fact_ranks'], static fn (?int $rank): bool => $rank !== null && $rank <= $k));
    $got = static fn (array $row): int => count(array_filter($row['fact_shipped']));

    $out = ['', sprintf('=== Multi-hop and global questions (the model gets at most %d chunks) ===', $settings->maxChunks)];

    foreach (['multi-hop' => 'Multi-hop', 'global' => 'Global'] as $kind => $label) {
        $set = array_values(array_filter($rows, static fn (array $row): bool => $row['kind'] === $kind));
        $facts = array_sum(array_map(static fn (array $row): int => count($row['facts']), $set));
        $complete = static fn (callable $found): int => count(array_filter($set, static fn (array $row): bool => $found($row) === count($row['facts'])));

        $out[] = sprintf('%s (%d questions, %d facts)', $label, count($set), $facts);
        $out[] = sprintf('  facts found        top 5 %s · top 10 %s · given to the model %s',
            $pct(array_sum(array_map(static fn (array $row): int => $within($row, 5), $set)), $facts),
            $pct(array_sum(array_map(static fn (array $row): int => $within($row, 10), $set)), $facts),
            $pct(array_sum(array_map($got, $set)), $facts));
        $out[] = sprintf('  every fact found   top 5 %s · top 10 %s · given to the model %s',
            $pct($complete(static fn (array $row): int => $within($row, 5)), count($set)),
            $pct($complete(static fn (array $row): int => $within($row, 10)), count($set)),
            $pct($complete($got), count($set)));
    }

    $out[] = '';
    $out[] = 'Facts the model did not get:';

    foreach ($rows as $row) {
        $missing = [];

        foreach ($row['facts'] as $index => $fact) {
            if (! $row['fact_shipped'][$index]) {
                $rank = $row['fact_ranks'][$index];
                $missing[] = '"'.Arr::first(Arr::wrap($fact)).'"'.($rank !== null ? " (rank {$rank})" : ' (not in the pool)');
            }
        }

        if ($missing !== []) {
            $out[] = "  - [{$row['kind']}] {$row['query']}: ".implode(', ', $missing);
        }
    }

    fwrite(STDOUT, implode(PHP_EOL, $out).PHP_EOL);
}

/**
 * Saved Q&A answers by meaning: the closest saved question's similarity for
 * messages that should get the answer and for ones that should not, with a
 * threshold sweep (EmbeddingProvider::qaSimilarityThreshold()).
 */
function retrievalEvalQaReport(Agent $agent): void
{
    $data = require __DIR__.'/fixtures/knowledge/qa.php';
    $entries = [];

    foreach ($data['entries'] as $entry) {
        $qa = app(CreateAgentQaAction::class)->execute($agent, new QaEntryData($entry['title'], $entry['questions'], $entry['answer']));
        app(EmbedQaQuestionsAction::class)->execute($qa);
        $entries[] = $qa->id;
    }

    $cast = app(EmbeddingStorage::class)->columnType();
    $positives = [];
    $negatives = [];
    $out = ['', '=== Saved answers by meaning ==='];

    foreach ($data['cases'] as $case) {
        $vector = '['.implode(',', app(EmbeddingsProviderInterface::class)->embedQuery($case['query'])).']';
        $closest = DB::selectOne("SELECT agent_question_answer_id, 1 - (embedding <=> ?::{$cast}) AS similarity FROM agent_question_answer_questions WHERE agent_id = ? ORDER BY embedding <=> ?::{$cast} LIMIT 1", [$vector, $agent->id, $vector]);
        $similarity = (float) ($closest->similarity ?? 0);
        $right = $case['match'] !== null && (int) ($closest->agent_question_answer_id ?? 0) === $entries[$case['match']];

        if ($case['match'] !== null) {
            $positives[] = $right ? $similarity : 0.0;
        } else {
            $negatives[] = $similarity;
        }

        $out[] = sprintf('  %.3f  %-5s %s', $similarity, $case['match'] === null ? 'none' : ($right ? 'right' : 'WRONG'), $case['query']);
    }

    $out[] = 'threshold   should match   should not match, blocked';

    for ($t = 0.50; $t < 0.96; $t += 0.05) {
        $out[] = sprintf('   %.2f        %5.1f%%          %5.1f%%', $t,
            100 * count(array_filter($positives, static fn (float $s): bool => $s >= $t)) / max(count($positives), 1),
            100 * count(array_filter($negatives, static fn (float $s): bool => $s < $t)) / max(count($negatives), 1));
    }

    fwrite(STDOUT, implode(PHP_EOL, $out).PHP_EOL);
}

/**
 * @param  list<array{query: string, kind: string, rank: int|null, shipped_hit: bool, shipped_count: int, score: float|null}>  $rows
 */
function retrievalEvalReport(array $rows, RetrievalSettingsData $settings): void
{
    $by = fn (string $kind): array => array_values(array_filter($rows, static fn (array $row): bool => $row['kind'] === $kind));
    $answers = $by('answer');
    $followUps = $by('follow-up');
    $uncovered = $by('uncovered');
    $pct = static fn (int $part, int $whole): string => $whole === 0 ? '  n/a' : sprintf('%5.1f%%', 100 * $part / $whole);
    $hitAt = static fn (array $set, int $k): int => count(array_filter($set, static fn (array $row): bool => $row['rank'] !== null && $row['rank'] <= $k));
    $mrr = static fn (array $set): float => $set === [] ? 0.0 : array_sum(array_map(static fn (array $row): float => $row['rank'] ? 1 / $row['rank'] : 0.0, $set)) / count($set);

    $reranker = app(Reranker::class);
    $out = [];
    $out[] = '';
    $out[] = '=== Knowledge retrieval eval ===';
    $out[] = sprintf('check: %s · max_chunks %d · hybrid %s',
        $reranker->isEnabled()
            ? 'reranker '.$reranker->model().', threshold '.RelevanceThresholds::knowledgeRerank($reranker)
            : 'similarity, threshold '.(RelevanceThresholds::knowledgeSimilarity() ?? 'none'),
        $settings->maxChunks, config('chatvia.vector.search.hybrid') ? 'on' : 'off');
    $out[] = retrievalEvalChunkLine();
    $out[] = 'enhancement: '.(env('EVAL_ENRICH') ? 'on'.(filled(env('EVAL_ENRICH_WITHOUT')) ? ', without the '.env('EVAL_ENRICH_WITHOUT') : '') : 'off');
    $out[] = '';
    $out[] = sprintf('Before the check   (%d questions): hit@1 %s  hit@3 %s  hit@5 %s  MRR %.3f',
        count($answers), $pct($hitAt($answers, 1), count($answers)), $pct($hitAt($answers, 3), count($answers)), $pct($hitAt($answers, 5), count($answers)), $mrr($answers));
    $out[] = sprintf('After the check    answered %s · uncovered left empty %s',
        $pct(count(array_filter($answers, static fn (array $row): bool => $row['shipped_hit'])), count($answers)),
        $pct(count(array_filter($uncovered, static fn (array $row): bool => $row['shipped_count'] === 0)), count($uncovered)));
    $out[] = sprintf('Follow-ups         (%d): hit@5 %s, answered %s',
        count($followUps), $pct($hitAt($followUps, 5), count($followUps)),
        $pct(count(array_filter($followUps, static fn (array $row): bool => $row['shipped_hit'])), count($followUps)));

    $positives = array_values(array_filter(array_column($answers, 'score'), static fn (?float $score): bool => $score !== null));
    $negatives = array_values(array_filter(array_column($uncovered, 'score'), static fn (?float $score): bool => $score !== null));

    if ($positives !== [] && $negatives !== []) {
        sort($positives);
        sort($negatives);
        $out[] = '';
        $out[] = sprintf('Scores of the right passage: min %.3f · p10 %.3f · median %.3f', $positives[0], $positives[(int) floor(count($positives) * 0.1)], $positives[intdiv(count($positives), 2)]);
        $out[] = sprintf('Best score, uncovered:       median %.3f · p90 %.3f · max %.3f', $negatives[intdiv(count($negatives), 2)], $negatives[(int) floor((count($negatives) - 1) * 0.9)], end($negatives));
        $out[] = '';
        $out[] = 'threshold   right passage kept   uncovered blocked';

        $best = null;

        for ($t = 0.05; $t < 0.96; $t += 0.05) {
            $kept = count(array_filter($positives, static fn (float $score): bool => $score >= $t)) / count($positives);
            $blocked = count(array_filter($negatives, static fn (float $score): bool => $score < $t)) / count($negatives);
            $out[] = sprintf('   %.2f         %5.1f%%             %5.1f%%', $t, 100 * $kept, 100 * $blocked);

            if ($best === null || $kept + $blocked > $best[1] + $best[2]) {
                $best = [$t, $kept, $blocked];
            }
        }

        $out[] = sprintf('best balance: %.2f (kept %.1f%%, blocked %.1f%%)', $best[0], 100 * $best[1], 100 * $best[2]);
    }

    $out[] = '';
    $out[] = 'Misses (before the check):';

    foreach (array_merge($answers, $followUps) as $row) {
        if ($row['rank'] === null || $row['rank'] > 5) {
            $out[] = '  - '.$row['query'].($row['rank'] ? " (rank {$row['rank']})" : ' (not in the pool)');
        }
    }

    fwrite(STDOUT, implode(PHP_EOL, $out).PHP_EOL);
}
