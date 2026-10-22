<?php

declare(strict_types=1);

namespace Domain\Knowledge\Embedding;

use Illuminate\Support\Facades\Http;

/**
 * Embeddings from Voyage AI's hosted API (`services.voyage`). Voyage adds its
 * own retrieval prompt for `input_type`, so documents and questions are sent
 * as `document` and `query`, and many documents go in one request.
 */
class Voyage extends AbstractEmbeddingsProvider
{
    /**
     * Texts per request. The API takes up to 1,000, but also caps the tokens
     * of one request (about 120K for voyage-4-large), which long chunks reach
     * well before that.
     */
    private const int BATCH_SIZE = 64;

    public function embed(string $text, bool $returnAsText = false): string|array
    {
        $embedding = $this->request([$text], 'document')[0];

        return $returnAsText ? '['.implode(',', $embedding).']' : $embedding;
    }

    public function embedBatch(array $texts): array
    {
        return collect($texts)
            ->chunk(self::BATCH_SIZE)
            ->flatMap(fn ($chunk): array => $this->request($chunk->values()->all(), 'document'))
            ->values()
            ->all();
    }

    protected function doEmbedQuery(string $text): array
    {
        return $this->request([$text], 'query')[0];
    }

    /**
     * One call to /embeddings; the vectors come back in the order of the texts.
     *
     * @param  list<string>  $texts
     * @return list<array<int, float>>
     */
    private function request(array $texts, string $inputType): array
    {
        return Http::withToken($this->getApiKey())
            ->throw()
            ->post($this->endpoint('/embeddings'), [
                'model' => $this->getModel(),
                'input' => $texts,
                'input_type' => $inputType,
                'output_dimension' => $this->getDimensions(),
            ])
            ->collect('data')
            ->sortBy('index')
            ->pluck('embedding')
            ->values()
            ->all();
    }

    /** The voyage-4 models return one of these sizes (`output_dimension`). */
    protected function supportsDimensions(int $dimensions): bool
    {
        return in_array($dimensions, [256, 512, 1024, 2048], true);
    }

    protected function getApiKey(): string
    {
        return (string) config('services.voyage.api_key');
    }

    protected function getBaseUrl(): string
    {
        return config('services.voyage.api_endpoint') ?: 'https://api.voyageai.com/v1';
    }

    protected function getModel(): string
    {
        return config('services.voyage.embedding_model') ?: 'voyage-4';
    }
}
