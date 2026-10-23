<?php

declare(strict_types=1);

namespace Tests\Support\Knowledge\Embedding;

use Closure;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Domain\Knowledge\Embedding\Services\EmbeddingStorage;

/**
 * Deterministic, offline stand-in for the real Qwen3 embeddings provider.
 *
 * The `testing` env has no Scaleway API key, so every embedding path (catalog
 * sync, tool routing, function indexing, KB ingestion) would otherwise hit the
 * network and trip the typed `getApiKey()`. Bound by default in TestCase so
 * tests are fast + deterministic; a test that needs specific vectors re-binds
 * one made with {@see constant()} or {@see directions()}.
 *
 * Every text it embeds is kept in {@see $texts}, in order. Its vectors have
 * the size the install stores ({@see EmbeddingStorage}), so the suite runs on
 * an install that picked another size too.
 */
class FakeEmbeddingsProvider implements EmbeddingsProviderInterface
{
    /** @var list<string> */
    public array $texts = [];

    /**
     * @param  (Closure(string): list<float>)|null  $vectors  the vector of a text; null for the text-varying default
     */
    public function __construct(
        private readonly ?Closure $vectors = null,
        private readonly string $model = 'fake-embeddings',
    ) {}

    /** Every text gets the same vector: `$value` in every dimension. */
    public static function constant(float $value = 0.1, string $model = 'fake-embeddings'): self
    {
        return new self(fn (string $text): array => array_fill(0, self::dimensions(), $value), $model);
    }

    /**
     * Vectors placed by hand, so the similarities are known: each listed text
     * gets its leading coordinates (every other dimension is 0), and any other
     * text gets `$otherwise`.
     *
     * @param  array<string, list<float>>  $directions
     * @param  list<float>  $otherwise
     */
    public static function directions(array $directions, array $otherwise = [0.0, 1.0]): self
    {
        return new self(fn (string $text): array => array_pad($directions[$text] ?? $otherwise, self::dimensions(), 0.0), 'directions');
    }

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
        return array_map($this->vectorFor(...), $texts);
    }

    public function getDimensions(): int
    {
        return self::dimensions();
    }

    public function getModelName(): string
    {
        return $this->model;
    }

    /** @return list<float> */
    private function vectorFor(string $text): array
    {
        $this->texts[] = $text;

        return $this->vectors instanceof Closure ? ($this->vectors)($text) : $this->textVaryingVector($text);
    }

    private static function dimensions(): int
    {
        return app(EmbeddingStorage::class)->dimensions();
    }

    /**
     * A deterministic, text-varying vector so distinct texts get distinct
     * (non-semantic) vectors — enough for retrieval-plumbing tests.
     *
     * @return list<float>
     */
    private function textVaryingVector(string $text): array
    {
        $seed = crc32($text);
        $vector = [];

        for ($i = 0; $i < self::dimensions(); $i++) {
            $vector[] = ((($seed >> ($i % 31)) & 1) === 1) ? 0.04 : 0.01;
        }

        return $vector;
    }
}
