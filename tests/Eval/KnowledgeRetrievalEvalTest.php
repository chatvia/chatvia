<?php

declare(strict_types=1);

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Domain\Knowledge\Embedding\Contracts\Reranker;
use Domain\Knowledge\Ingestion\Actions\Chunks\CreateSourceChunksAction;
use Domain\Knowledge\Ingestion\Contracts\TextSplitter;
use Domain\Knowledge\Ingestion\Data\Chunking\ChunkPagesData;
use Domain\Knowledge\Ingestion\Data\Chunking\PageGroupData;
use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Domain\Knowledge\Ingestion\Enums\ChunkEnhancement;
use Domain\Knowledge\Ingestion\Enums\ContentKind;
use Domain\Knowledge\Ingestion\Services\Chunking\BuiltinTextSplitter;
use Domain\Knowledge\Retrieval\Data\RetrievalSettingsData;
use Domain\Knowledge\Retrieval\Data\RetrievedChunkData;
use Domain\Knowledge\Retrieval\Queries\HybridChunkSearch;
use Domain\Knowledge\Retrieval\Services\KnowledgeBase;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Tests\Support\FakeEmbeddingsProvider;

/**
 * OFFLINE EVAL — not a pass/fail gate. It measures knowledge retrieval against
 * a labelled set (fixtures/knowledge/cases.php over the three chunking samples
 * and an Arabic page; format in README.md) and prints a scoreboard:
 *
 *  - before the relevance check (the hybrid search's ranking): hit@1/3/5, MRR;
 *  - after it (what the model gets, KnowledgeBase::search): how many questions
 *    got their passage, and how many uncovered questions got nothing;
 *  - the check's scores for the right passage and for uncovered questions, with
 *    a threshold sweep — this is how the default thresholds are chosen.
 *
 * It runs the REAL embeddings provider and reranker (the test fakes would make
 * every number noise), so it needs keys and network, and skips itself without
 * them:
 *
 *     SCALEWAYAI_API_KEY=… vendor/bin/pest --testsuite=Eval --filter=knowledge
 *
 * Knobs (env, one run): VECTOR_SEARCH_RELEVANCE_THRESHOLD, VECTOR_SEARCH_MAX_CHUNKS,
 * EVAL_ENRICH=1 (enrich chunks with the Smart model first; needs a chat model).
 */
beforeEach(function (): void {
    if (filled($key = env('SCALEWAYAI_API_KEY'))) {
        config(['services.scalewayai.api_key' => $key]);
    }

    if (filled($key = env('COHERE_API_KEY'))) {
        config(['services.cohere.api_key' => $key]);
    }

    app()->forgetInstance(EmbeddingsProviderInterface::class);
    $provider = app(EmbeddingsProviderInterface::class);

    if ($provider instanceof FakeEmbeddingsProvider) {
        $this->markTestSkipped('real embeddings provider not bound; cannot run a semantic eval against the fake.');
    }

    try {
        $provider->embedQuery('health check');
    } catch (Throwable $e) {
        $this->markTestSkipped('real embeddings unavailable (configure the embedding provider + API key): '.$e->getMessage());
    }

    app()->instance(EmbeddingsProviderInterface::class, $provider);

    app()->forgetInstance(Reranker::class);
    app()->instance(Reranker::class, app(Reranker::class));

    // The real splitter, as production splits these documents.
    app()->instance(TextSplitter::class, new BuiltinTextSplitter);
});

/** The eval's documents: path => how it is split. */
function knowledgeEvalDocuments(): array
{
    return [
        resource_path('chunking/markdown.md') => ContentKind::Markdown,
        resource_path('chunking/text.txt') => ContentKind::Text,
        resource_path('chunking/transcript.txt') => ContentKind::Transcript,
        __DIR__.'/fixtures/knowledge/arabic.md' => ContentKind::Markdown,
    ];
}

/** An agent that knows the eval documents, split and embedded for real. */
function knowledgeEvalAgent(): Agent
{
    $agent = Agent::factory()->create();
    $enhancement = env('EVAL_ENRICH') ? ChunkEnhancement::Text : ChunkEnhancement::Off;

    foreach (knowledgeEvalDocuments() as $path => $kind) {
        $text = (string) file_get_contents($path);

        $source = SourceText::query()->create([
            'workspace_id' => $agent->workspace_id,
            'filepath' => 'eval/'.basename($path),
            'excerpt' => mb_substr($text, 0, 200),
            'length' => mb_strlen($text),
            'checksum' => md5($path.$text),
            'status' => SourceStatus::Completed,
        ]);
        $agent->texts()->attach($source->id);

        $pages = PageData::fromParts(app(TextSplitter::class)->split($text, $kind));

        app(CreateSourceChunksAction::class)->execute(new ChunkPagesData(
            source: $source,
            group: new PageGroupData($pages),
            enhancement: $enhancement,
        ));
    }

    return $agent;
}

/** The search question of a case: a follow-up is searched as the user typed it until the turn rewrites it. */
function knowledgeEvalQuery(array $case): string
{
    return $case['query'];
}

/**
 * The 1-based rank of the first chunk that holds the expected text, or null.
 *
 * @param  list<RetrievedChunkData>  $chunks
 */
function knowledgeEvalRank(array $chunks, string $expect): ?int
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
function knowledgeEvalScores(string $query, array $candidates): ?array
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
    $agent = knowledgeEvalAgent();
    $settings = RetrievalSettingsData::fromConfig();
    $search = app(HybridChunkSearch::class);
    $embeddings = app(EmbeddingsProviderInterface::class);
    $pool = max($settings->candidateLimit(), 25);

    $rows = [];

    foreach ($cases as $case) {
        $query = knowledgeEvalQuery($case);
        $vector = '['.implode(',', $embeddings->embedQuery($query)).']';
        $scope = $search->scope($agent, $settings->exactMaxChunks);
        $candidates = $search->search($agent, $query, $vector, $pool, $scope, $settings->accuracy);
        $shipped = app(KnowledgeBase::class)->search($agent, $query);
        $scores = knowledgeEvalScores($query, $candidates);

        $rank = $case['expect'] !== null ? knowledgeEvalRank($candidates, $case['expect']) : null;

        $rows[] = [
            'query' => $case['query'],
            'kind' => $case['expect'] === null ? 'uncovered' : (isset($case['history']) ? 'follow-up' : 'answer'),
            'rank' => $rank,
            'shipped_hit' => $case['expect'] !== null && knowledgeEvalRank($shipped, $case['expect']) !== null,
            'shipped_count' => count($shipped),
            // The right passage's score, or the best score of an uncovered question.
            'score' => $scores === null ? null : ($case['expect'] === null
                ? ($scores === [] ? 0.0 : max($scores))
                : ($rank !== null ? ($scores[$rank - 1] ?? null) : null)),
        ];
    }

    knowledgeEvalReport($rows, $settings);

    expect($rows)->toHaveCount(count($cases));
})->group('eval');

/**
 * @param  list<array{query: string, kind: string, rank: int|null, shipped_hit: bool, shipped_count: int, score: float|null}>  $rows
 */
function knowledgeEvalReport(array $rows, RetrievalSettingsData $settings): void
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
    $out[] = sprintf('reranker: %s (%s) · max_chunks %d · relevance_threshold %s · hybrid %s',
        class_basename($reranker), $reranker->isEnabled() ? 'on' : 'off', $settings->maxChunks,
        $settings->relevanceThreshold ?? 'none', config('chatvia.vector.search.hybrid') ? 'on' : 'off');
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
