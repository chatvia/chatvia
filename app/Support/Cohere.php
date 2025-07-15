<?php

namespace App\Support;

use App\Data\Chunk;
use Exception;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class Cohere
{
    public function embed(string $text)
    {
        return Http::withToken(config('services.cohere.api_key'))
            ->throw()
            ->post($this->endpoint('/embed'), [
                'model' => 'embed-v4.0',
                'texts' => [$text],
                'input_type' => 'search_document',
                'embedding_types' => ['float'],
            ])->json('embeddings.float.0');
    }

    public function rerank(string $query, array $documents): array
    {
        $rerank = Http::withToken(config('services.cohere.api_key'))
            ->throw()
            ->post($this->endpoint('/rerank'), [
                'model' => 'rerank-v3.5',
                'query' => $query,
                'max_tokens_per_doc' => 1000,
                'documents' => $documents,
            ])->json('results');

        return collect($rerank)->pluck('index')->toArray();
    }

    private function endpoint(?string $append = null): string
    {
        return rtrim(config('services.cohere.api_endpoint'), '/').$append;
    }

    public function bulkEmbed(Collection $chunks): Collection
    {
        $responses = Http::pool(function (Pool $pool) use ($chunks) {
            /** @var Chunk $chunk */
            foreach ($chunks as $chunk) {
                $pool
                    ->as($chunk->chunk_id)
                    ->withToken(config('services.cohere.api_key'))
                    ->asJson()
                    ->post($this->endpoint('/embed'), [
                        'model' => 'embed-v4.0',
                        'texts' => [$chunk->original_content],
                        'input_type' => 'search_document',
                        'embedding_types' => ['float'],
                    ]);
            }
        });

        return collect($chunks)->map(function (Chunk $chunk) use ($responses) {
            $response = $responses[$chunk->chunk_id];

            logger($response);

            if ($response->ok()) {
                $chunk->embedding = $response->json('embeddings.float.0');
            } else {
                throw new Exception("Failed to get embedding for chunk {$chunk->chunk_id}: ".
                    ($response instanceof Exception ? $response->getMessage() : 'Unknown error'));
            }

            return $chunk;
        });
    }
}
