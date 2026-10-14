<?php

declare(strict_types=1);

namespace Domain\Knowledge\Embedding;

use Illuminate\Support\Facades\Http;

/**
 * Embeddings from OpenAI's API (`services.openai`): the text-embedding-3
 * models, which shorten their vectors to the size asked for (`dimensions`)
 * and return them normalized.
 */
class OpenAi extends AbstractEmbeddingsProvider
{
    /** Texts per request, well under the API's limits (2048 inputs, 300k tokens). */
    private const int BATCH_SIZE = 256;

    public function embed(string $text, bool $returnAsText = false): string|array
    {
        $embedding = $this->embedBatch([$text])[0] ?? [];

        if ($returnAsText) {
            return '['.implode(',', $embedding).']';
        }

        return $embedding;
    }

    /**
     * @param  list<string>  $texts
     * @return list<array<int, float>>
     */
    public function embedBatch(array $texts): array
    {
        $vectors = [];

        foreach (array_chunk(array_values($texts), self::BATCH_SIZE) as $batch) {
            $data = (array) Http::withToken($this->getApiKey())
                ->throw()
                ->post($this->endpoint('/embeddings'), [
                    'model' => $this->getModel(),
                    'input' => $batch,
                    'dimensions' => $this->getDimensions(),
                ])
                ->json('data');

            // Each item carries its `index`: sort so the result lines up with the
            // input. The sort is stable, so items without one keep their order.
            usort($data, fn (array $a, array $b): int => ($a['index'] ?? 0) <=> ($b['index'] ?? 0));

            array_push($vectors, ...array_column($data, 'embedding'));
        }

        return $vectors;
    }

    /** text-embedding-3-large returns up to 3072 dimensions, text-embedding-3-small up to 1536. */
    protected function supportsDimensions(int $dimensions): bool
    {
        return $dimensions >= 1 && $dimensions <= (str_contains($this->getModel(), 'large') ? 3072 : 1536);
    }

    protected function getApiKey(): string
    {
        return (string) config('services.openai.api_key');
    }

    protected function getBaseUrl(): string
    {
        return (string) config('services.openai.api_endpoint');
    }

    protected function getModel(): string
    {
        return (string) config('services.openai.embedding_model');
    }
}
