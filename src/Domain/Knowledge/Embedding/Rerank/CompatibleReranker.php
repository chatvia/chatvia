<?php

declare(strict_types=1);

namespace Domain\Knowledge\Embedding\Rerank;

use Domain\Knowledge\Embedding\Contracts\Reranker;
use Domain\Knowledge\Embedding\Data\RerankedDocumentData;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * A cross-encoder reranker behind the common `/rerank` API that OpenRouter,
 * Cohere and self-hosted servers (vLLM, Infinity, Jina-compatible) share:
 * `{model, query, documents, top_n}` in, `results[{index, relevance_score}]`
 * out. The results are sorted here, best first, whatever order the server
 * sends. Built by {@see RerankerFactory} for the provider set in the admin
 * panel (Settings → AI models).
 */
final readonly class CompatibleReranker implements Reranker
{
    /**
     * @param  array<string, mixed>  $extraPayload  provider-specific fields, such as Cohere's `max_tokens_per_doc`
     */
    public function __construct(
        private string $baseUrl,
        private ?string $apiKey,
        private string $model,
        private bool $keyRequired = true,
        private array $extraPayload = [],
        private int $timeoutSeconds = 10,
    ) {}

    public function rerank(string $query, array $documents, ?int $topN = null): array
    {
        if ($documents === []) {
            return [];
        }

        if ($this->keyRequired && blank($this->apiKey)) {
            throw new RuntimeException('The reranker has no API key (Settings → AI providers).');
        }

        if ($this->baseUrl === '' || $this->model === '') {
            throw new RuntimeException('The reranker has no address or model (Settings → AI models).');
        }

        $request = Http::acceptJson()
            ->timeout($this->timeoutSeconds)
            ->retry(2, 200, throw: false);

        if (filled($this->apiKey)) {
            $request = $request->withToken($this->apiKey);
        }

        $results = $request->post($this->baseUrl.'/rerank', [
            'model' => $this->model,
            'query' => $query,
            'documents' => array_values($documents),
            'top_n' => $topN ?? count($documents),
            ...$this->extraPayload,
        ])->throw()->json('results') ?? [];

        $ranked = array_map(
            RerankedDocumentData::fromResult(...),
            array_values(array_filter((array) $results, is_array(...))),
        );

        usort($ranked, static fn (RerankedDocumentData $a, RerankedDocumentData $b): int => $b->relevanceScore <=> $a->relevanceScore);

        return $topN === null ? $ranked : array_slice($ranked, 0, max($topN, 0));
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function model(): string
    {
        return $this->model;
    }
}
