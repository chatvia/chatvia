<?php

namespace Domain\Knowledge\Embedding;

use Domain\Llm\Providers\Scaleway;
use Illuminate\Support\Arr;
use Support\Http\ProviderRetry;

/**
 * Embeddings from the open-weight Qwen3-Embedding model, served by an OpenAI-
 * compatible `/embeddings` endpoint (Scaleway Generative APIs by default).
 * Queries get the instruction prefix the model was trained with; documents do
 * not. Token usage is added to the open workspace scope.
 */
class Qwen3 extends AbstractEmbeddingsProvider
{
    /**
     * Instruction prefix recommended by the Qwen3-Embedding paper for
     * retrieval queries. Documents are embedded without any prefix.
     */
    public const string QUERY_INSTRUCTION = 'Given a search query, retrieve relevant passages that answer the query';

    public function embed(string $text, bool $returnAsText = false): string|array
    {
        $response = ProviderRetry::request()->withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer {$this->getApiKey()}",
        ])
            ->throw()
            ->post($this->endpoint('/embeddings'), [
                'model' => $this->getModel(),
                'input' => $text,
                'dimensions' => $this->getDimensions(),
            ]);

        $embedding = $response->json('data.0.embedding');

        if ($returnAsText) {
            return '['.Arr::join($embedding, ',').']';
        }

        return $embedding;
    }

    /**
     * One request, many documents — the OpenAI-compatible /embeddings
     * endpoint accepts an input array and returns data[] in input order.
     *
     * @param  list<string>  $texts
     * @return list<array<int, float>>
     */
    public function embedBatch(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        $response = ProviderRetry::request()->withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer {$this->getApiKey()}",
        ])
            ->throw()
            ->post($this->endpoint('/embeddings'), [
                'model' => $this->getModel(),
                'input' => array_values($texts),
                'dimensions' => $this->getDimensions(),
            ]);

        $data = $response->json('data');

        // data[] carries an `index` per item; sort defensively so the result
        // is positionally aligned with the input even if the API reorders.
        usort($data, fn (array $a, array $b): int => $a['index'] <=> $b['index']);

        return array_column($data, 'embedding');
    }

    protected function doEmbedQuery(string $text): array
    {
        /** @var array<int, float> $vector */
        $vector = $this->embed('Instruct: '.self::QUERY_INSTRUCTION."\nQuery: {$text}");

        return $vector;
    }

    /** Qwen3-Embedding returns any size up to 4096 (Matryoshka). */
    protected function supportsDimensions(int $dimensions): bool
    {
        return $dimensions >= 32 && $dimensions <= 4096;
    }

    protected function getApiKey(): string
    {
        $key = config('services.scalewayai.api_key');

        // Fail with a clear message rather than a confusing "Return value must
        // be of type string, null returned" TypeError when the key is unset
        // (e.g. a misconfigured prod env). Callers catch + degrade gracefully.
        if (blank($key)) {
            throw new \RuntimeException('Scaleway AI API key is not configured (services.scalewayai.api_key).');
        }

        return $key;
    }

    protected function getBaseUrl(): string
    {
        return Scaleway::baseUrl();
    }

    protected function getModel(): string
    {
        return (string) config('services.scalewayai.embedding_model');
    }
}
