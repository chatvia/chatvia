<?php

namespace Domain\Knowledge\Ingestion\Services\Chunking\ChunkFit;

use Domain\Knowledge\Ingestion\Data\Chunking\ChunkerChunkData;
use Domain\Knowledge\Ingestion\Data\Chunking\ChunkerConfigData;
use Domain\Knowledge\Ingestion\Data\Chunking\ChunkerServerData;
use Domain\Knowledge\Ingestion\Services\Chunking\ChunkFit\Exceptions\ChunkFitException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * HTTP client for the chunker (github.com/chatvia/chunker, `services.chunkfit`):
 * either the hosted service at chunk.fit, which needs an API key, or a
 * self-hosted chunker (the Docker stack runs one), which takes no key. The
 * strategy and its options are set in the admin panel (Settings → Chunking).
 * Used through ChunkFitTextSplitter, and by that page for its preview and to
 * ask the server what it can run.
 */
class ChunkFitClient
{
    /** Host of the hosted service, the only one that receives the API key. */
    private const string HOSTED_HOST = 'chunk.fit';

    /** How long to wait for the quick calls: what the server runs, token counts. */
    private const int SERVER_TIMEOUT_SECONDS = 5;

    /** The most texts the chunker counts in one request. */
    private const int TOKEN_BATCH = 2048;

    /**
     * Whether it can be used: the hosted service needs its API key, a
     * self-hosted chunker only its address.
     */
    public function isAvailable(): bool
    {
        return $this->usesHostedService() ? filled($this->apiKey()) : $this->baseUrl() !== '';
    }

    /**
     * Split text with the strategy set in the admin panel.
     *
     * @return list<string> the ordered chunks
     *
     * @throws ChunkFitException
     */
    public function split(string $text): array
    {
        return $this->chunk($text, ChunkerConfigData::fromConfig())
            ->map(fn (ChunkerChunkData $chunk): string => $chunk->text)
            ->values()
            ->all();
    }

    /**
     * Split text with the given strategy (POST /v1/chunk).
     *
     * @return Collection<int, ChunkerChunkData> the ordered, non-empty chunks
     *
     * @throws ChunkFitException
     */
    public function chunk(string $text, ChunkerConfigData $config): Collection
    {
        if (trim($text) === '') {
            return collect();
        }

        try {
            $response = $this->http($this->timeoutSeconds())
                ->retry(2, 250, throw: false)
                ->post('/v1/chunk', [
                    'text' => $text,
                    'chunker' => $config->toChunker(),
                    'encoding' => $config->encoding,
                ]);
        } catch (ChunkFitException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new ChunkFitException('Failed to reach the chunker: '.$e->getMessage(), $e->getCode(), previous: $e);
        }

        $this->ensureSuccessful($response);

        return $this->chunks($response);
    }

    /**
     * Count the tokens of each text with the given tokenizer (POST
     * /v1/tokens), in one request per batch.
     *
     * @param  list<string>  $texts
     * @return list<int> in the order of $texts
     *
     * @throws ChunkFitException also when the server cannot count (an older
     *                           chunker answers 404)
     */
    public function countTokens(array $texts, string $encoding): array
    {
        $counts = [];

        foreach (array_chunk($texts, self::TOKEN_BATCH) as $batch) {
            try {
                $response = $this->http(self::SERVER_TIMEOUT_SECONDS)->post('/v1/tokens', ['input' => $batch, 'encoding' => $encoding]);
            } catch (ChunkFitException $e) {
                throw $e;
            } catch (Throwable $e) {
                throw new ChunkFitException('Failed to reach the chunker: '.$e->getMessage(), $e->getCode(), previous: $e);
            }

            $this->ensureSuccessful($response);

            $data = $response->json('data');
            $byIndex = collect(is_array($data) ? $data : [])
                ->filter(fn (mixed $item): bool => is_array($item) && is_int($item['index'] ?? null) && is_int($item['tokens'] ?? null))
                ->mapWithKeys(fn (array $item): array => [$item['index'] => $item['tokens']]);

            foreach (array_keys($batch) as $index) {
                if (! $byIndex->has($index)) {
                    throw new ChunkFitException('The chunker did not count every text.');
                }

                $counts[] = (int) $byIndex->get($index);
            }
        }

        return $counts;
    }

    /**
     * The strategies the server runs and whether it can embed (GET
     * /v1/engines). Never throws: an unreachable server is reported in the
     * result.
     */
    public function server(): ChunkerServerData
    {
        try {
            $response = $this->http(self::SERVER_TIMEOUT_SECONDS)->get('/v1/engines');
            $this->ensureSuccessful($response);
        } catch (Throwable $e) {
            return ChunkerServerData::unreachable($e->getMessage());
        }

        $chunkers = $response->json('chunkers');
        $strategies = collect(is_array($chunkers) ? $chunkers : [])
            ->filter(fn (mixed $chunker): bool => is_array($chunker) && is_string($chunker['engine'] ?? null) && is_string($chunker['strategy'] ?? null))
            ->map(fn (array $chunker): string => "{$chunker['engine']}.{$chunker['strategy']}")
            ->values()
            ->all();

        return new ChunkerServerData(
            strategies: $strategies,
            encodings: array_values(array_filter((array) $response->json('encodings'), is_string(...))),
            embeddingsConfigured: $response->json('embeddings.configured') === true,
            embeddingsModel: is_string($response->json('embeddings.model')) ? $response->json('embeddings.model') : null,
        );
    }

    /**
     * @throws ChunkFitException when the hosted service has no API key
     */
    private function http(int $timeoutSeconds): PendingRequest
    {
        $headers = ['Accept' => 'application/json'];

        // The API key belongs to the hosted service: a self-hosted chunker
        // never receives it, even when one is saved in the admin panel.
        if ($this->usesHostedService()) {
            $apiKey = $this->apiKey();

            if ($apiKey === null || $apiKey === '') {
                throw new ChunkFitException(
                    'chunk.fit is not configured: add its API key in the admin panel (Settings → Chunking), or point the chunker address to your own chunker.',
                );
            }

            $headers['Authorization'] = 'Bearer '.$apiKey;
        }

        return Http::baseUrl($this->baseUrl())
            ->timeout($timeoutSeconds)
            ->withHeaders($headers)
            ->asJson();
    }

    /**
     * @throws ChunkFitException
     */
    private function ensureSuccessful(Response $response): void
    {
        if ($response->failed()) {
            throw new ChunkFitException(sprintf(
                'The chunker returned %d: %s',
                $response->status(),
                $this->summarizeError($response),
            ), $response->status());
        }
    }

    /**
     * @return Collection<int, ChunkerChunkData>
     *
     * @throws ChunkFitException
     */
    private function chunks(Response $response): Collection
    {
        $chunks = $response->json('chunks');

        if (! is_array($chunks)) {
            throw new ChunkFitException('The chunker response has no "chunks" list.');
        }

        return collect($chunks)
            ->filter(fn (mixed $chunk): bool => is_array($chunk) && is_string($chunk['text'] ?? null) && trim($chunk['text']) !== '')
            ->map(fn (array $chunk): ChunkerChunkData => new ChunkerChunkData(
                text: trim($chunk['text']),
                tokenCount: (int) ($chunk['token_count'] ?? 0),
            ))
            ->values();
    }

    /**
     * The chunker explains a refused request in `detail`: a message, or a list
     * of validation errors with the field (`loc`) and the reason (`msg`).
     */
    private function summarizeError(Response $response): string
    {
        $detail = $response->json('detail');

        if (is_string($detail)) {
            return $detail;
        }

        if (is_array($detail)) {
            return collect($detail)
                ->map(fn (mixed $error): string => is_array($error)
                    ? trim(implode('.', array_slice((array) ($error['loc'] ?? []), 1)).': '.($error['msg'] ?? ''), ': ')
                    : (string) json_encode($error))
                ->implode('; ');
        }

        return mb_substr((string) $response->body(), 0, 500);
    }

    private function apiKey(): ?string
    {
        return config('services.chunkfit.api_key');
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.chunkfit.base_url'), '/');
    }

    private function usesHostedService(): bool
    {
        $host = strtolower((string) parse_url($this->baseUrl(), PHP_URL_HOST));

        return $host === self::HOSTED_HOST || str_ends_with($host, '.'.self::HOSTED_HOST);
    }

    private function timeoutSeconds(): int
    {
        return (int) config('services.chunkfit.timeout', 120);
    }
}
