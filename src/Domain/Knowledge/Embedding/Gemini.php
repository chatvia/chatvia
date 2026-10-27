<?php

declare(strict_types=1);

namespace Domain\Knowledge\Embedding;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Support\Http\ProviderRetry;

/**
 * Embeddings from Google's Gemini API (`services.gemini`), gemini-embedding-001
 * by default. Documents and questions are embedded with their own task type.
 * A vector shorter than the model's full size comes back unnormalized, so it
 * is scaled to length 1, as the other services return theirs.
 */
class Gemini extends AbstractEmbeddingsProvider
{
    /** Texts per request: batchEmbedContents takes at most 100. */
    private const int BATCH_SIZE = 100;

    public function embed(string $text, bool $returnAsText = false): string|array
    {
        $embedding = $this->request([$text], 'RETRIEVAL_DOCUMENT')[0] ?? [];

        if ($returnAsText) {
            return '['.Arr::join($embedding, ',').']';
        }

        return $embedding;
    }

    /**
     * @param  list<string>  $texts
     * @return list<array<int, float>>
     */
    public function embedBatch(array $texts): array
    {
        return $this->request(array_values($texts), 'RETRIEVAL_DOCUMENT');
    }

    protected function doEmbedQuery(string $text): array
    {
        return $this->request([$text], 'RETRIEVAL_QUERY')[0] ?? [];
    }

    /** gemini-embedding-001 returns 128 to 3072 dimensions. */
    protected function supportsDimensions(int $dimensions): bool
    {
        return $dimensions >= 128 && $dimensions <= 3072;
    }

    /**
     * @param  list<string>  $texts
     * @return list<array<int, float>>
     */
    private function request(array $texts, string $taskType): array
    {
        $model = Str::startsWith($this->getModel(), 'models/') ? $this->getModel() : "models/{$this->getModel()}";
        $vectors = [];

        foreach (array_chunk($texts, self::BATCH_SIZE) as $batch) {
            $embeddings = (array) ProviderRetry::request()->withHeaders(['x-goog-api-key' => $this->getApiKey()])
                ->throw()
                ->post($this->endpoint("/{$model}:batchEmbedContents"), [
                    'requests' => array_map(fn (string $text): array => [
                        'model' => $model,
                        'content' => ['parts' => [['text' => $text]]],
                        'taskType' => $taskType,
                        'outputDimensionality' => $this->getDimensions(),
                    ], $batch),
                ])
                ->json('embeddings');

            foreach ($embeddings as $embedding) {
                $vectors[] = $this->normalize($embedding['values']);
            }
        }

        return $vectors;
    }

    /**
     * @param  list<float>  $vector
     * @return list<float>
     */
    private function normalize(array $vector): array
    {
        $length = sqrt(array_sum(array_map(fn (float $value): float => $value * $value, $vector)));

        return $length > 0 ? array_map(fn (float $value): float => $value / $length, $vector) : $vector;
    }

    protected function getApiKey(): string
    {
        return (string) config('services.gemini.api_key');
    }

    protected function getBaseUrl(): string
    {
        return (string) config('services.gemini.api_endpoint');
    }

    protected function getModel(): string
    {
        return (string) config('services.gemini.embedding_model');
    }
}
