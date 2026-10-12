<?php

namespace Tests\Unit\Services\Chunking\ChunkFit;

use Domain\Knowledge\Ingestion\Data\Chunking\ChunkerConfigData;
use Domain\Knowledge\Ingestion\Enums\ChunkerStrategy;
use Domain\Knowledge\Ingestion\Services\Chunking\ChunkFit\ChunkFitClient;
use Domain\Knowledge\Ingestion\Services\Chunking\ChunkFit\Exceptions\ChunkFitException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The chunker client: POST /v1/chunk with the strategy set in the admin panel,
 * GET /v1/engines for what the server can run, and the API key only for the
 * hosted chunk.fit.
 */
class ChunkFitClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.chunkfit.api_key', 'test-key');
        config()->set('services.chunkfit.base_url', 'https://chunk.fit');
        config()->set('services.chunkfit.timeout', 30);
        config()->set('services.chunkfit.strategy', 'langchain.recursive');
        config()->set('chatvia.ai.tokenizer', 'o200k_base');
        config()->set('services.chunkfit.options', [
            'chunk_size' => 400,
            'chunk_overlap' => 0,
            'length_unit' => 'tokens',
            'language' => 'markdown',
            // Not an option of the recursive strategy: never sent to it.
            'threshold' => 0.7,
        ]);
    }

    public function test_returns_no_chunks_for_blank_text_without_calling_the_api(): void
    {
        Http::fake();

        $this->assertSame([], (new ChunkFitClient)->split('   '));

        Http::assertNothingSent();
    }

    public function test_posts_the_configured_strategy_with_only_its_own_options(): void
    {
        Http::fake([
            'chunk.fit/v1/chunk' => Http::response([
                'chunks' => [
                    ['index' => 0, 'text' => 'First chunk text.', 'token_count' => 4],
                    ['index' => 1, 'text' => 'Second chunk text.', 'token_count' => 4],
                ],
            ]),
        ]);

        $chunks = (new ChunkFitClient)->split('Long body of text.');

        $this->assertSame(['First chunk text.', 'Second chunk text.'], $chunks);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://chunk.fit/v1/chunk'
            && $request->hasHeader('Authorization', 'Bearer test-key')
            && $request['text'] === 'Long body of text.'
            && $request['encoding'] === 'o200k_base'
            && $request['chunker'] === [
                'engine' => 'langchain',
                'strategy' => 'recursive',
                'chunk_size' => 400,
                'chunk_overlap' => 0,
                'length_unit' => 'tokens',
                'language' => 'markdown',
            ]);
    }

    public function test_returns_the_token_count_of_each_chunk_and_drops_empty_ones(): void
    {
        Http::fake([
            'chunk.fit/*' => Http::response(['chunks' => [
                ['text' => '  padded  ', 'token_count' => 2],
                ['text' => '   ', 'token_count' => 0],
                ['text' => 'normal', 'token_count' => 1],
            ]]),
        ]);

        $chunks = (new ChunkFitClient)->chunk('text', new ChunkerConfigData(ChunkerStrategy::ChonkieSemantic, threshold: 0.6));

        $this->assertSame([['text' => 'padded', 'tokenCount' => 2], ['text' => 'normal', 'tokenCount' => 1]], $chunks->toArray());

        Http::assertSent(fn (Request $request): bool => $request['chunker'] === ['engine' => 'chonkie', 'strategy' => 'semantic', 'threshold' => 0.6]);
    }

    public function test_explains_a_refused_request_with_the_chunker_validation_errors(): void
    {
        Http::fake([
            'chunk.fit/*' => Http::response(['detail' => [
                ['loc' => ['body', 'chunker', 'langchain', 'recursive', 'chunk_overlap'], 'msg' => 'Value error, chunk_overlap must be smaller than chunk_size'],
            ]], 422),
        ]);

        $this->expectException(ChunkFitException::class);
        $this->expectExceptionMessage('The chunker returned 422: chunker.langchain.recursive.chunk_overlap: Value error, chunk_overlap must be smaller than chunk_size');

        (new ChunkFitClient)->split('text');
    }

    public function test_raises_when_the_response_has_no_chunks(): void
    {
        Http::fake(['chunk.fit/*' => Http::response(['unexpected' => 'shape'])]);

        $this->expectException(ChunkFitException::class);
        $this->expectExceptionMessage('has no "chunks" list');

        (new ChunkFitClient)->split('text');
    }

    public function test_raises_when_the_hosted_service_has_no_api_key(): void
    {
        config()->set('services.chunkfit.api_key');

        $client = new ChunkFitClient;

        $this->assertFalse($client->isAvailable());
        $this->expectException(ChunkFitException::class);
        $this->expectExceptionMessage('chunk.fit is not configured');

        $client->split('text');
    }

    public function test_calls_a_self_hosted_chunker_without_any_api_key(): void
    {
        // A key saved for the hosted service must never reach a self-hosted one.
        config()->set('services.chunkfit.base_url', 'http://chunker:8000/');

        Http::fake(['chunker:8000/v1/chunk' => Http::response(['chunks' => [['text' => 'Only chunk.', 'token_count' => 3]]])]);

        $client = new ChunkFitClient;

        $this->assertTrue($client->isAvailable());
        $this->assertSame(['Only chunk.'], $client->split('Some text.'));

        Http::assertSent(fn (Request $request): bool => $request->url() === 'http://chunker:8000/v1/chunk'
            && ! $request->hasHeader('Authorization'));
    }

    public function test_a_self_hosted_chunker_needs_only_its_address(): void
    {
        config()->set('services.chunkfit.api_key');
        config()->set('services.chunkfit.base_url', 'http://chunker:8000');

        $this->assertTrue((new ChunkFitClient)->isAvailable());
    }

    public function test_reports_what_the_server_can_run(): void
    {
        Http::fake(['chunk.fit/v1/engines' => Http::response([
            'chunkers' => [
                ['engine' => 'langchain', 'strategy' => 'recursive', 'requires_embeddings' => false],
                ['engine' => 'chonkie', 'strategy' => 'semantic', 'requires_embeddings' => true],
            ],
            'encodings' => ['cl100k_base'],
            'embeddings' => ['configured' => false, 'model' => null],
        ])]);

        $server = (new ChunkFitClient)->server();

        $this->assertTrue($server->isReachable());
        $this->assertSame(['langchain.recursive', 'chonkie.semantic'], $server->strategies);
        $this->assertNull($server->cannotRun(ChunkerStrategy::LangchainRecursive));
        $this->assertStringContainsString('embeddings model', (string) $server->cannotRun(ChunkerStrategy::ChonkieSemantic));
        $this->assertStringContainsString('Not offered', (string) $server->cannotRun(ChunkerStrategy::LlamaIndexSentence));
    }

    public function test_reports_an_unreachable_server_instead_of_throwing(): void
    {
        Http::fake(['chunk.fit/*' => Http::response('Bad gateway', 502)]);

        $server = (new ChunkFitClient)->server();

        $this->assertFalse($server->isReachable());
        $this->assertStringContainsString('502', (string) $server->error);
        // Nothing is known, so nothing is marked as unavailable.
        $this->assertNull($server->cannotRun(ChunkerStrategy::ChonkieSemantic));
    }
}
