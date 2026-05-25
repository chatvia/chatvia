<?php

namespace Tests\Unit\Services\Chunking\ChunkFit;

use Domain\Ingestion\Services\Chunking\ChunkFit\ChunkFitClient;
use Domain\Ingestion\Services\Chunking\ChunkFit\Exceptions\ChunkFitException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChunkFitClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.chunkfit.api_key', 'test-key');
        config()->set('services.chunkfit.base_url', 'https://chunk.fit/api/v1');
        config()->set('services.chunkfit.timeout', 30);
        config()->set('services.chunkfit.tokenizer', 'cl100k_base');
    }

    public function test_returns_empty_array_for_blank_text_without_calling_api(): void
    {
        Http::fake();

        $client = new ChunkFitClient;

        $this->assertSame([], $client->splitSentences('   ', chunkSize: 800));

        Http::assertNothingSent();
    }

    public function test_posts_to_llamaindex_sentence_endpoint_with_expected_envelope(): void
    {
        Http::fake([
            'chunk.fit/api/v1/llama-index/splitters/sentence' => Http::response([
                'chunks' => ['First chunk text.', 'Second chunk text.'],
                'chunk_count' => 2,
                'metadata' => ['splitter_type' => 'SentenceSplitter'],
            ], 200),
        ]);

        $client = new ChunkFitClient;
        $chunks = $client->splitSentences('Long body of text.', chunkSize: 800, chunkOverlap: 0);

        $this->assertSame(['First chunk text.', 'Second chunk text.'], $chunks);

        Http::assertSent(function (Request $request) {
            return $request->method() === 'POST'
                && $request->url() === 'https://chunk.fit/api/v1/llama-index/splitters/sentence'
                && $request->hasHeader('Authorization', 'Bearer test-key')
                && $request['text'] === 'Long body of text.'
                && $request['config']['chunk_size'] === 800
                && $request['config']['chunk_overlap'] === 0
                && $request['config']['tokenizer'] === 'cl100k_base';
        });
    }

    public function test_trims_chunks_and_drops_empty_entries(): void
    {
        Http::fake([
            'chunk.fit/*' => Http::response([
                'chunks' => ['  padded  ', '', '   ', 'normal'],
                'chunk_count' => 4,
                'metadata' => [],
            ], 200),
        ]);

        $client = new ChunkFitClient;
        $chunks = $client->splitSentences('text', chunkSize: 800);

        $this->assertSame(['padded', 'normal'], $chunks);
    }

    public function test_raises_a_typed_exception_when_api_returns_an_error(): void
    {
        Http::fake([
            'chunk.fit/*' => Http::response(['detail' => 'invalid chunk_size'], 422),
        ]);

        $client = new ChunkFitClient;

        $this->expectException(ChunkFitException::class);
        $this->expectExceptionMessage('chunk.fit returned 422: invalid chunk_size');

        $client->splitSentences('text', chunkSize: 800);
    }

    public function test_raises_when_api_response_is_malformed(): void
    {
        Http::fake([
            'chunk.fit/*' => Http::response(['unexpected' => 'shape'], 200),
        ]);

        $client = new ChunkFitClient;

        $this->expectException(ChunkFitException::class);
        $this->expectExceptionMessage('missing "chunks" array');

        $client->splitSentences('text', chunkSize: 800);
    }

    public function test_raises_when_api_key_is_missing(): void
    {
        config()->set('services.chunkfit.api_key', null);

        $client = new ChunkFitClient;

        $this->expectException(ChunkFitException::class);
        $this->expectExceptionMessage('CHUNK_FIT_API_KEY');

        $client->splitSentences('text', chunkSize: 800);
    }
}
