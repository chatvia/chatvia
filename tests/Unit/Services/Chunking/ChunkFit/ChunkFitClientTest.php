<?php

namespace Tests\Unit\Services\Chunking\ChunkFit;

use Domain\Knowledge\Ingestion\Services\Chunking\ChunkFit\ChunkFitClient;
use Domain\Knowledge\Ingestion\Services\Chunking\ChunkFit\Exceptions\ChunkFitException;
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
        config()->set('services.chunkfit.by_title.max_characters', 2000);
        config()->set('services.chunkfit.by_title.new_after_n_chars', 1500);
        config()->set('services.chunkfit.by_title.combine_text_under_n_chars', 1000);
    }

    public function test_returns_empty_array_for_blank_text_without_calling_api(): void
    {
        Http::fake();

        $client = new ChunkFitClient;

        $this->assertSame([], $client->splitByTitle('   '));

        Http::assertNothingSent();
    }

    public function test_posts_to_the_by_title_endpoint_with_expected_envelope(): void
    {
        Http::fake([
            'chunk.fit/api/v1/unstructured/splitters/text' => Http::response([
                'chunks' => ['First chunk text.', 'Second chunk text.'],
                'chunk_count' => 2,
                'metadata' => [],
            ], 200),
        ]);

        $client = new ChunkFitClient;
        $chunks = $client->splitByTitle('Long body of text.');

        $this->assertSame(['First chunk text.', 'Second chunk text.'], $chunks);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://chunk.fit/api/v1/unstructured/splitters/text'
            && $request->hasHeader('Authorization', 'Bearer test-key')
            && $request['text'] === 'Long body of text.'
            && $request['config']['max_characters'] === 2000
            && $request['config']['new_after_n_chars'] === 1500
            && $request['config']['combine_text_under_n_chars'] === 1000
            && $request['config']['overlap'] === 0);
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
        $chunks = $client->splitByTitle('text');

        $this->assertSame(['padded', 'normal'], $chunks);
    }

    public function test_raises_a_typed_exception_when_api_returns_an_error(): void
    {
        Http::fake([
            'chunk.fit/*' => Http::response(['detail' => 'invalid max_characters'], 422),
        ]);

        $client = new ChunkFitClient;

        $this->expectException(ChunkFitException::class);
        $this->expectExceptionMessage('chunk.fit returned 422: invalid max_characters');

        $client->splitByTitle('text');
    }

    public function test_raises_when_api_response_is_malformed(): void
    {
        Http::fake([
            'chunk.fit/*' => Http::response(['unexpected' => 'shape'], 200),
        ]);

        $client = new ChunkFitClient;

        $this->expectException(ChunkFitException::class);
        $this->expectExceptionMessage('missing "chunks" array');

        $client->splitByTitle('text');
    }

    public function test_raises_when_api_key_is_missing(): void
    {
        config()->set('services.chunkfit.api_key');

        $client = new ChunkFitClient;

        $this->expectException(ChunkFitException::class);
        $this->expectExceptionMessage('CHUNK_FIT_API_KEY');

        $client->splitByTitle('text');
    }
}
