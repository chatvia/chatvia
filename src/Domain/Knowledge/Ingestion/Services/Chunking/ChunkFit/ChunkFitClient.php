<?php

namespace Domain\Knowledge\Ingestion\Services\Chunking\ChunkFit;

use Domain\Knowledge\Ingestion\Services\Chunking\ChunkFit\Exceptions\ChunkFitException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class ChunkFitClient
{
    /**
     * Split text using LlamaIndex SentenceSplitter via chunk.fit.
     *
     * Sentence-boundary aware, token-budgeted in tiktoken (cl100k_base by
     * default — same currency as TikToken::countTokens used elsewhere in
     * the Chunker pipeline).
     *
     * @return string[] the ordered chunks
     *
     * @throws ChunkFitException
     */
    public function splitSentences(
        string $text,
        int $chunkSize,
        int $chunkOverlap = 0,
    ): array {
        if (trim($text) === '') {
            return [];
        }

        $response = $this->request(
            endpoint: '/llama-index/splitters/sentence',
            body: [
                'text' => $text,
                'config' => [
                    'chunk_size' => $chunkSize,
                    'chunk_overlap' => $chunkOverlap,
                    'tokenizer' => $this->tokenizer(),
                ],
            ],
        );

        return $this->extractChunks($response);
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

    private function request(string $endpoint, array $body): Response
    {
        $apiKey = $this->apiKey();

        if ($apiKey === null || $apiKey === '') {
            throw new ChunkFitException(
                'chunk.fit is not configured: missing CHUNK_FIT_API_KEY.',
            );
        }

        try {
            $response = Http::baseUrl($this->baseUrl())
                ->timeout($this->timeoutSeconds())
                ->retry(2, 250, throw: false)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$apiKey,
                    'Accept' => 'application/json',
                ])
                ->asJson()
                ->post($endpoint, $body);
        } catch (Throwable $e) {
            throw new ChunkFitException(
                'Failed to reach chunk.fit: '.$e->getMessage(),
                previous: $e,
            );
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
            fn ($chunk): bool => $chunk !== null && $chunk !== '',
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

    private function timeoutSeconds(): int
    {
        return (int) config('services.chunkfit.timeout', 30);
    }

    private function tokenizer(): string
    {
        return (string) config('services.chunkfit.tokenizer', 'cl100k_base');
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
