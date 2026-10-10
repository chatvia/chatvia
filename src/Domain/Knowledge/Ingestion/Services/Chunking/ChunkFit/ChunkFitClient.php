<?php

namespace Domain\Knowledge\Ingestion\Services\Chunking\ChunkFit;

use Domain\Knowledge\Ingestion\Services\Chunking\ChunkFit\Exceptions\ChunkFitException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * HTTP client for the chunk.fit text splitter (`services.chunkfit`): either
 * the hosted service at chunk.fit, which needs an API key, or a self-hosted
 * chunker (github.com/chatvia/chunker, which the Docker stack runs), which
 * takes no key. Used through ChunkFitTextSplitter.
 */
class ChunkFitClient
{
    /** Host of the hosted service, the only one that receives the API key. */
    private const string HOSTED_HOST = 'chunk.fit';

    /**
     * Whether it can be used: the hosted service needs its API key, a
     * self-hosted chunker only its address.
     */
    public function isAvailable(): bool
    {
        return $this->usesHostedService() ? filled($this->apiKey()) : $this->baseUrl() !== '';
    }

    /**
     * Chunk document text by section/title via chunk.fit's Unstructured
     * `chunk_by_title` strategy.
     *
     * Unlike the raw Markdown header splitter (which starts a new chunk at every
     * heading and never merges, shredding clause-heavy pages into dozens of
     * sub-100-token fragments), this partitions the text into title-led sections
     * and then COMBINES small adjacent sections up to `combine_text_under_n_chars`
     * while capping size at `max_characters`. The result is a small set of
     * coherent, evenly-sized, fragment-free chunks — empirically the best fit for
     * the FireCrawl Markdown output across home/marketing and clause-heavy
     * (terms/refund/pricing) pages alike.
     *
     * NOTE: all size knobs are measured in CHARACTERS.
     *
     * @return string[] the ordered chunks
     *
     * @throws ChunkFitException
     */
    public function splitByTitle(string $text): array
    {
        if (trim($text) === '') {
            return [];
        }

        $response = $this->request(
            endpoint: '/unstructured/splitters/text',
            body: [
                'text' => $text,
                'config' => [
                    'max_characters' => $this->maxCharacters(),
                    'new_after_n_chars' => $this->newAfterNChars(),
                    'combine_text_under_n_chars' => $this->combineUnderNChars(),
                    'overlap' => 0,
                ],
            ],
        );

        return $this->extractChunks($response);
    }

    /** @param  array<string, mixed>  $body  the JSON request body */
    private function request(string $endpoint, array $body): Response
    {
        $headers = ['Accept' => 'application/json'];

        // The API key belongs to the hosted service: a self-hosted chunker
        // never receives it, even when one is saved in the admin panel.
        if ($this->usesHostedService()) {
            $apiKey = $this->apiKey();

            if ($apiKey === null || $apiKey === '') {
                throw new ChunkFitException(
                    'chunk.fit is not configured: add its API key in the admin panel (Settings → Knowledge), or set CHUNK_FIT_BASE_URL to your own chunker.',
                );
            }

            $headers['Authorization'] = 'Bearer '.$apiKey;
        }

        try {
            $response = Http::baseUrl($this->baseUrl())
                ->timeout($this->timeoutSeconds())
                ->retry(2, 250, throw: false)
                ->withHeaders($headers)
                ->asJson()
                ->post($endpoint, $body);
        } catch (Throwable $e) {
            throw new ChunkFitException('Failed to reach chunk.fit: '.$e->getMessage(), $e->getCode(), previous: $e);
        }

        if ($response->failed()) {
            throw new ChunkFitException(sprintf(
                'chunk.fit returned %d: %s',
                $response->status(),
                $this->summarizeError($response),
            ));
        }

        return $response;
    }

    /**
     * @return string[]
     */
    private function extractChunks(Response $response): array
    {
        $chunks = $response->json('chunks');

        if (! is_array($chunks)) {
            throw new ChunkFitException('chunk.fit response missing "chunks" array.');
        }

        return array_values(array_filter(
            array_map(fn ($chunk): ?string => is_string($chunk) ? trim($chunk) : null, $chunks),
            fn (?string $chunk): bool => $chunk !== null && $chunk !== '',
        ));
    }

    private function summarizeError(Response $response): string
    {
        $body = $response->json();

        if (is_array($body) && isset($body['detail'])) {
            return is_string($body['detail']) ? $body['detail'] : json_encode($body['detail']);
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
        return (int) config('services.chunkfit.timeout', 30);
    }

    private function maxCharacters(): int
    {
        return (int) config('services.chunkfit.by_title.max_characters', 2000);
    }

    private function newAfterNChars(): int
    {
        return (int) config('services.chunkfit.by_title.new_after_n_chars', 1500);
    }

    private function combineUnderNChars(): int
    {
        return (int) config('services.chunkfit.by_title.combine_text_under_n_chars', 1000);
    }
}
