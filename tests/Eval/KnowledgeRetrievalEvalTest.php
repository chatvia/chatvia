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
use Domain\Knowledge\Ingestion\Services\Chunking\BuiltinTextSplitter;
use Domain\Knowledge\Retrieval\Data\RetrievalSettingsData;
use Domain\Knowledge\Retrieval\Data\RetrievedChunkData;
use Domain\Knowledge\Retrieval\Queries\HybridChunkSearch;
use Domain\Knowledge\Retrieval\Services\KnowledgeBase;
use Domain\Knowledge\Source\Qa\Actions\CreateAgentQaAction;
use Domain\Knowledge\Source\Qa\Actions\EmbedQaQuestionsAction;
use Domain\Knowledge\Source\Qa\Data\QaEntryData;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Llm\Operations\Query\QueryRewriter;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
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
    $settings = RetrievalSettingsData::fromConfig();
    $search = app(HybridChunkSearch::class);
    $embeddings = app(EmbeddingsProviderInterface::class);
    $pool = max($settings->candidateLimit(), 25);

    $rows = [];
    $factRows = [];

    foreach ($cases as $case) {
        $query = retrievalEvalQuery($case);
        $vector = '['.implode(',', $embeddings->embedQuery($query)).']';
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
