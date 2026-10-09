<?php

namespace Domain\Knowledge\Embedding;

use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Base for the embedding providers: the cached query-embedding entry point and
 * the shared configuration helpers. Documents and queries are embedded
 * separately because some models expect a different input for each.
 */
abstract class AbstractEmbeddingsProvider implements EmbeddingsProviderInterface
{
    /**
     * Cached entry point for query embeddings. Same (model, text) always
     * yields the same vector, so we cache for 24h to absorb retries,
     * concurrent handlers, and common short messages. Subclasses with
     * query-side conventions (Qwen3 instruction prefix, etc.) override
     * `doEmbedQuery()`; the cache key is keyed on the raw text so the
     * prefix doesn't leak into the key.
     */
    final public function embedQuery(string $text, bool $returnAsText = false): string|array
    {
        $vector = Cache::remember(
            'embed:query:'.$this->getModel().':'.sha1($text),
            now()->addDay(),
            fn (): array => $this->doEmbedQuery($text),
        );

        return $returnAsText ? '['.implode(',', $vector).']' : $vector;
    }

    /**
     * Hook for provider-specific query-side conventions. Default: embed
     * the query as a plain document. Override to add instruction prefixes
     * or switch input_type flags.
     *
     * @return array<int, float>
     */
    protected function doEmbedQuery(string $text): array
    {
        /** @var array<int, float> $vector */
        $vector = $this->embed($text);

        return $vector;
    }

    /**
     * Portable fallback: one call per text. Providers whose API accepts an
     * input array (Qwen3 / OpenAI-compatible) override this with a single
     * batched request.
     *
     * @param  list<string>  $texts
     * @return list<array<int, float>>
     */
    public function embedBatch(array $texts): array
    {
        return array_map(fn (string $text): array => (array) $this->embed($text), $texts);
    }

    /**
     * Get the API endpoint.
     *
     * @param  string|null  $path  Optional path to append
     * @return string The full endpoint URL
     */
    protected function endpoint(?string $path = null): string
    {
        return rtrim($this->getBaseUrl(), '/').$path;
    }

    /**
     * Get the API key for the provider.
     *
     * @return string The API key
     */
    abstract protected function getApiKey(): string;

    /**
     * Get the base URL for the provider.
     *
     * @return string The base URL
     */
    abstract protected function getBaseUrl(): string;

    /**
     * Get the model name to use for embeddings.
     *
     * @return string The model name
     */
    abstract protected function getModel(): string;

    /**
     * Get the model name used for embeddings.
     *
     * @return string The model name
     */
    public function getModelName(): string
    {
        return $this->getModel();
    }
}
