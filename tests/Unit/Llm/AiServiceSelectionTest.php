<?php

use Domain\Knowledge\Embedding\Cohere;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Domain\Knowledge\Embedding\Contracts\Reranker;
use Domain\Knowledge\Embedding\Exceptions\EmbeddingDimensionsMismatchException;
use Domain\Knowledge\Embedding\Gemini;
use Domain\Knowledge\Embedding\Jina;
use Domain\Knowledge\Embedding\Mistral as MistralEmbeddings;
use Domain\Knowledge\Embedding\OpenAi;
use Domain\Knowledge\Embedding\OpenRouter as OpenRouterEmbeddings;
use Domain\Knowledge\Embedding\Qwen3;
use Domain\Knowledge\Embedding\Rerank\CompatibleReranker;
use Domain\Knowledge\Embedding\Rerank\NullReranker;
use Domain\Knowledge\Ingestion\Contracts\DocumentParser;
use Domain\Knowledge\Ingestion\Contracts\TextSplitter;
use Domain\Knowledge\Ingestion\Services\Chunking\BuiltinTextSplitter;
use Domain\Knowledge\Ingestion\Services\Parsing\LocalDocumentParser;
use Domain\Knowledge\Ingestion\Services\Parsing\MistralDocumentParser;
use Domain\Llm\Contracts\Transcriber;
use Domain\Llm\Enums\LlmProviderEnum;
use Domain\Llm\Exceptions\LlmProviderUnavailableException;
use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Operations\Media\NullTranscriber;
use Domain\Llm\Operations\Media\OpenAiTranscriber;
use Domain\Llm\Operations\Media\ScalewayTranscriber;
use Domain\Llm\Providers\Contracts\ProviderAdapter;
use Domain\Llm\Providers\Mistral;
use Domain\Llm\Providers\OpenAiCompatible;
use Domain\Llm\Providers\OpenRouter;
use Domain\Llm\Providers\Scaleway;
use Domain\Llm\Services\DocumentLlm;
use Domain\Llm\Services\LlmProviders;
use Domain\Llm\Services\ProviderAdapters;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Each AI step is bound from config('chatvia.ai.*') in DomainServiceProvider.
 * `auto` must pick the hosted service when its API key is set (production
 * keeps today's behaviour) and the local or "off" implementation when it is
 * not (a self-hosted install works without those accounts).
 */
beforeEach(function (): void {
    // TestCase pins offline fakes; these tests check the real bindings.
    app()->forgetInstance(EmbeddingsProviderInterface::class);
    app()->forgetInstance(Reranker::class);
    app()->forgetInstance(TextSplitter::class);

    config([
        'services.mistral.api_key' => null,
        'services.scalewayai.api_key' => null,
        'services.cohere.api_key' => null,
        'services.openai.api_key' => null,
        'services.openrouter.api_key' => null,
        'chatvia.ai.document_parser' => 'auto',
        'chatvia.ai.reranker' => 'auto',
        'chatvia.ai.transcriber' => 'auto',
        'chatvia.ai.document_llm' => 'auto',
        'chatvia.ai.models.embedding' => 'qwen',
    ]);
});

it('uses only local or "off" services when no vendor key is set', function (): void {
    expect(app(DocumentParser::class))->toBeInstanceOf(LocalDocumentParser::class)
        ->and(app(Transcriber::class))->toBeInstanceOf(NullTranscriber::class)
        ->and(app(DocumentLlm::class)->isAvailable())->toBeFalse();
});

it('keeps the hosted services when their keys are set, as in production', function (): void {
    config([
        'services.mistral.api_key' => 'mistral-key',
        'services.scalewayai.api_key' => 'scaleway-key',
    ]);

    $documentLlm = app(DocumentLlm::class);

    expect(app(DocumentParser::class))->toBeInstanceOf(MistralDocumentParser::class)
        ->and(app(DocumentParser::class)->name())->toBe(config('services.mistral.ocr_model'))
        ->and(app(Transcriber::class))->toBeInstanceOf(ScalewayTranscriber::class)
        ->and(app(Reranker::class))->toBeInstanceOf(NullReranker::class)
        ->and(app(EmbeddingsProviderInterface::class))->toBeInstanceOf(Qwen3::class)
        ->and($documentLlm->isAvailable())->toBeTrue()
        ->and($documentLlm->provider())->toBeInstanceOf(Mistral::class)
        ->and($documentLlm->slug())->toBe('mistral');
});

it('always splits text with the built-in splitter, which needs no outside service', function (): void {
    expect(app(TextSplitter::class))->toBeInstanceOf(BuiltinTextSplitter::class)
        ->and(app(TextSplitter::class)->name())->toBe('builtin');
});

it('lets an explicit driver override auto', function (): void {
    config([
        'services.mistral.api_key' => 'mistral-key',
        'services.scalewayai.api_key' => 'scaleway-key',
        'chatvia.ai.document_parser' => 'none',
        'chatvia.ai.transcriber' => 'none',
        'chatvia.ai.reranker' => 'none',
        'chatvia.ai.document_llm' => 'none',
    ]);

    expect(app(DocumentParser::class))->toBeInstanceOf(LocalDocumentParser::class)
        ->and(app(Transcriber::class))->toBeInstanceOf(NullTranscriber::class)
        ->and(app(Reranker::class))->toBeInstanceOf(NullReranker::class)
        ->and(app(DocumentLlm::class)->isAvailable())->toBeFalse();
});

it('reranks through OpenRouter with its key, when it is connected, by default', function (): void {
    config(['services.openrouter.api_key' => 'sk-or-test', 'services.cohere.api_key' => 'cohere-key']);

    $reranker = app(Reranker::class);

    expect($reranker)->toBeInstanceOf(CompatibleReranker::class)
        ->and($reranker->isEnabled())->toBeTrue()
        ->and($reranker->model())->toBe('cohere/rerank-4-pro');
});

it('falls back to Cohere, then to no reranker, for auto', function (): void {
    config(['services.cohere.api_key' => 'cohere-key']);

    expect(app(Reranker::class)->model())->toBe('rerank-v4.0-pro');

    config(['services.cohere.api_key' => null]);

    expect(app(Reranker::class))->toBeInstanceOf(NullReranker::class);
});

it('uses the chosen reranker and model', function (string $driver, array $config, string $model): void {
    config(['chatvia.ai.reranker' => $driver, ...$config]);

    expect(app(Reranker::class)->model())->toBe($model);
})->with([
    'an OpenRouter model' => ['openrouter', ['chatvia.ai.rerank.openrouter_model' => 'qwen/qwen3-reranker-8b'], 'qwen/qwen3-reranker-8b'],
    'a Cohere model' => ['cohere', ['services.cohere.rerank_model' => 'rerank-v4.0-fast'], 'rerank-v4.0-fast'],
    'a self-hosted server' => ['custom', ['chatvia.ai.rerank.custom.url' => 'http://reranker:8000/v1', 'chatvia.ai.rerank.custom.model' => 'Qwen/Qwen3-Reranker-4B'], 'Qwen/Qwen3-Reranker-4B'],
]);

it('transcribes with OpenAI Whisper when chosen, or when only an OpenAI key is set', function (): void {
    config(['services.openai.api_key' => 'openai-key']);

    expect(app(Transcriber::class))->toBeInstanceOf(OpenAiTranscriber::class);

    config(['services.scalewayai.api_key' => 'scaleway-key']);

    expect(app(Transcriber::class))->toBeInstanceOf(ScalewayTranscriber::class);

    config(['chatvia.ai.transcriber' => 'openai']);

    expect(app(Transcriber::class))->toBeInstanceOf(OpenAiTranscriber::class);
});

it('fails fast on an unknown driver name', function (string $key, string $contract): void {
    config(["chatvia.ai.{$key}" => 'llamaparse']);

    expect(fn () => app($contract))->toThrow(InvalidArgumentException::class, "chatvia.ai.{$key}");
})->with([
    ['document_parser', DocumentParser::class],
    ['reranker', Reranker::class],
    ['transcriber', Transcriber::class],
    ['document_llm', DocumentLlm::class],
]);

it('refuses a document LLM provider that cannot read PDF documents', function (): void {
    config(['chatvia.ai.document_llm' => 'scaleway']);

    expect(fn () => app(DocumentLlm::class))->toThrow(InvalidArgumentException::class, 'Use auto, mistral, none');
});

it('builds the client of each provider kind', function (LlmProviderEnum $kind, string $class): void {
    connectAiProviders();

    expect(app(LlmProviders::class)->forProvider(LlmProvider::factory()->kind($kind)->make()))->toBeInstanceOf($class);
})->with([
    'scaleway' => [LlmProviderEnum::SCALEWAY, Scaleway::class],
    'mistral' => [LlmProviderEnum::MISTRAL, Mistral::class],
    'openrouter' => [LlmProviderEnum::OPENROUTER, OpenRouter::class],
    'openai' => [LlmProviderEnum::OPENAI, OpenAiCompatible::class],
    'gemini' => [LlmProviderEnum::GOOGLE, OpenAiCompatible::class],
    'groq' => [LlmProviderEnum::GROQ, OpenAiCompatible::class],
    'custom server' => [LlmProviderEnum::CUSTOM, OpenAiCompatible::class],
]);

it('refuses a provider kind it has no client for, one that is off, or one without its key', function (LlmProvider $provider): void {
    expect(fn () => app(LlmProviders::class)->forProvider($provider))->toThrow(LlmProviderUnavailableException::class);
})->with([
    'no client yet' => fn () => LlmProvider::factory()->kind(LlmProviderEnum::ANTHROPIC)->make(),
    'turned off' => fn () => LlmProvider::factory()->disabled()->make(),
    'no key' => fn () => LlmProvider::factory()->kind(LlmProviderEnum::SCALEWAY)->make(),
]);

it('lets only a server of your own keep a key and an address on its row', function (): void {
    // In a savepoint, so the refused insert leaves the test's transaction usable.
    expect(fn () => DB::transaction(fn () => LlmProvider::factory()->kind(LlmProviderEnum::SCALEWAY)->create(['api_key' => 'scw-key'])))
        ->toThrow(QueryException::class, 'llm_providers_key_on_custom_only');

    expect(LlmProvider::factory()->create(['api_key' => 'local-key'])->fresh()->api_key)->toBe('local-key');
});

it('stores every provider kind, and only Mistral reads PDF documents', function (): void {
    foreach (LlmProviderEnum::cases() as $kind) {
        expect(LlmProvider::factory()->kind($kind)->create()->fresh()->kind)->toBe($kind);
    }

    $readers = array_filter(app(ProviderAdapters::class)->all(), fn (ProviderAdapter $adapter): bool => $adapter->readsDocuments());

    expect(array_map(fn (ProviderAdapter $adapter): LlmProviderEnum => $adapter->kind(), array_values($readers)))
        ->toBe([LlmProviderEnum::MISTRAL]);
});

it('fails fast when the embedding provider cannot return the size the database stores', function (string $provider, int $stored): void {
    config([
        'chatvia.ai.models.embedding' => $provider,
        'chatvia.embeddings.dimensions' => $stored,
    ]);

    expect(fn () => app(EmbeddingsProviderInterface::class))
        ->toThrow(EmbeddingDimensionsMismatchException::class, "returns 1024 dimensions, but the database stores {$stored}");
})->with([
    'mistral is fixed at 1024' => ['mistral', 1536],
    'jina stops at 1024' => ['jina', 2048],
    'cohere has fixed sizes' => ['cohere', 768],
    'openai small stops at 1536' => ['openai', 2048],
    'openai small on openrouter stops at 1536' => ['openrouter', 2048],
    'gemini starts at 128' => ['google', 64],
]);

it('asks the embedding provider for the size the database stores', function (string $provider, string $class, int $stored): void {
    config([
        'chatvia.ai.models.embedding' => $provider,
        'chatvia.embeddings.dimensions' => $stored,
    ]);

    $embeddings = app(EmbeddingsProviderInterface::class);

    expect($embeddings)->toBeInstanceOf($class)
        ->and($embeddings->getDimensions())->toBe($stored);
})->with([
    'cohere 1536' => ['cohere', Cohere::class, 1536],
    'jina 512' => ['jina', Jina::class, 512],
    'qwen 2048' => ['qwen', Qwen3::class, 2048],
    'mistral 1024' => ['mistral', MistralEmbeddings::class, 1024],
    'openai 1536' => ['openai', OpenAi::class, 1536],
    'openrouter 1536' => ['openrouter', OpenRouterEmbeddings::class, 1536],
    'gemini 768' => ['google', Gemini::class, 768],
]);
