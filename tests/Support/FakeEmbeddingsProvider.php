<?php

namespace Tests\Support;

use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;

/**
 * Deterministic, offline stand-in for the real Qwen3 embeddings provider.
 *
 * The `testing` env has no Scaleway API key, so every embedding path (catalog
 * sync, tool routing, function indexing, KB ingestion) would otherwise hit the
 * network and trip the typed `getApiKey()`. Bound by default in TestCase so
 * tests are fast + deterministic; a test that needs specific vectors re-binds.
 */
class FakeEmbeddingsProvider implements EmbeddingsProviderInterface
{
    public function embed(string $text, bool $returnAsText = false): string|array
    {
        $vector = $this->vectorFor($text);

        return $returnAsText ? '['.implode(',', $vector).']' : $vector;
    }

    public function embedQuery(string $text, bool $returnAsText = false): string|array
    {
        return $this->embed($text, $returnAsText);
    }

    public function embedBatch(array $texts): array
    {
        return array_map(fn (string $text): array => $this->vectorFor($text), $texts);
    }

    /**
     * @param  array<int, string>  $documents
     * @return array<int, array{index: int, relevance_score: float}>
     */
    public function rerank(string $query, array $documents, ?int $topN = null): ?array
    {
        // Identity rerank — keep input order with descending scores — so the
        // rerank path is exercised without needing semantic ordering. Scores stay
        // high (1.0, 0.99, ...) so the tool relevance gate is a passthrough here.
        $ranked = array_map(
            static fn (int $i): array => ['index' => $i, 'relevance_score' => 1.0 - ($i * 0.01)],
            array_keys(array_values($documents)),
        );

        return $topN === null ? $ranked : array_slice($ranked, 0, $topN);
    }

    public function supportsReranking(): bool
    {
        return true;
    }

    public function getDimensions(): int
    {
        return 1024;
    }

    public function getModelName(): string
    {
        return 'fake-embeddings';
    }

    /**
     * A deterministic, text-varying vector so distinct texts get distinct
     * (non-semantic) vectors — enough for retrieval-plumbing tests.
     *
     * @return list<float>
     */
    private function vectorFor(string $text): array
    {
        $seed = crc32($text);
        $vector = [];

        for ($i = 0; $i < 1024; $i++) {
            $vector[] = ((($seed >> ($i % 31)) & 1) === 1) ? 0.04 : 0.01;
        }

        return $vector;
    }
}
