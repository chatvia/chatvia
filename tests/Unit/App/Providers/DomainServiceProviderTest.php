<?php

declare(strict_types=1);

use Domain\Knowledge\Embedding\Cohere;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Domain\Knowledge\Embedding\Contracts\Reranker;
use Domain\Knowledge\Embedding\Exceptions\EmbeddingDimensionsMismatchException;
use Domain\Knowledge\Embedding\Exceptions\EmbeddingServiceMissingException;
use Domain\Knowledge\Embedding\Gemini;
use Domain\Knowledge\Embedding\Jina;
use Domain\Knowledge\Embedding\Mistral as MistralEmbeddings;
use Domain\Knowledge\Embedding\OpenAi;
use Domain\Knowledge\Embedding\OpenRouter as OpenRouterEmbeddings;
use Domain\Knowledge\Embedding\Qwen3;
use Domain\Knowledge\Embedding\Rerank\CompatibleReranker;
use Domain\Knowledge\Embedding\Rerank\NullReranker;
use Domain\Knowledge\Embedding\UnconfiguredEmbeddings;
use Domain\Knowledge\Embedding\Voyage;
use Domain\Knowledge\Ingestion\Contracts\DocumentParser;
use Domain\Knowledge\Ingestion\Services\Parsing\AzureDocumentParser;
use Domain\Knowledge\Ingestion\Services\Parsing\LocalDocumentParser;
use Domain\Knowledge\Ingestion\Services\Parsing\MistralDocumentParser;
use Domain\Llm\Contracts\RealtimeCalls;
use Domain\Llm\Contracts\Transcriber;
use Domain\Llm\Operations\Media\NullTranscriber;
use Domain\Llm\Operations\Media\OpenAiTranscriber;
use Domain\Llm\Operations\Media\ScalewayTranscriber;
use Domain\Llm\Operations\Realtime\NullRealtimeCalls;
use Domain\Llm\Operations\Realtime\OpenAiRealtimeCalls;

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

    config([
        'services.mistral.api_key' => null,
        'services.scalewayai.api_key' => null,
        'services.cohere.api_key' => null,
        'services.openai.api_key' => null,
        'services.openrouter.api_key' => null,
        'chatvia.ai.document_parser' => 'auto',
        'chatvia.ai.reranker' => 'auto',
        'chatvia.ai.transcriber' => 'auto',
        'chatvia.ai.models.embedding' => 'qwen',
    ]);
});

it('uses only local or "off" services when no vendor key is set', function (): void {
    expect(app(DocumentParser::class))->toBeInstanceOf(LocalDocumentParser::class)
        ->and(app(Transcriber::class))->toBeInstanceOf(NullTranscriber::class);
});

it('keeps the hosted services when their keys are set, as in production', function (): void {
    config([
        'services.mistral.api_key' => 'mistral-key',
        'services.scalewayai.api_key' => 'scaleway-key',
    ]);

    expect(app(DocumentParser::class))->toBeInstanceOf(MistralDocumentParser::class)
        ->and(app(DocumentParser::class)->name())->toBe(config('services.mistral.ocr_model'))
        ->and(app(Transcriber::class))->toBeInstanceOf(ScalewayTranscriber::class)
        ->and(app(Reranker::class))->toBeInstanceOf(NullReranker::class)
        ->and(app(EmbeddingsProviderInterface::class)->provider())->toBeInstanceOf(Qwen3::class);
});

it('reads documents with Azure when only Azure is connected', function (): void {
    config([
        'services.azure_document_intelligence.key' => 'az-key',
        'services.azure_document_intelligence.endpoint' => 'https://docs.cognitiveservices.azure.com',
    ]);

    expect(app(DocumentParser::class))->toBeInstanceOf(AzureDocumentParser::class);
});

it('lets an explicit driver override auto', function (): void {
    config([
        'services.mistral.api_key' => 'mistral-key',
        'services.scalewayai.api_key' => 'scaleway-key',
        'chatvia.ai.document_parser' => 'none',
        'chatvia.ai.transcriber' => 'none',
        'chatvia.ai.reranker' => 'none',
    ]);

    expect(app(DocumentParser::class))->toBeInstanceOf(LocalDocumentParser::class)
        ->and(app(Transcriber::class))->toBeInstanceOf(NullTranscriber::class)
        ->and(app(Reranker::class))->toBeInstanceOf(NullReranker::class);
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

it('transcribes with the service whose key is set, unless one is chosen', function (array $config, string $transcriber): void {
    config($config);

    expect(app(Transcriber::class))->toBeInstanceOf($transcriber);
})->with([
    'only an OpenAI key' => [['services.openai.api_key' => 'openai-key'], OpenAiTranscriber::class],
    'OpenAI and Scaleway keys' => [['services.openai.api_key' => 'openai-key', 'services.scalewayai.api_key' => 'scaleway-key'], ScalewayTranscriber::class],
    'OpenAI chosen' => [['services.openai.api_key' => 'openai-key', 'services.scalewayai.api_key' => 'scaleway-key', 'chatvia.ai.transcriber' => 'openai'], OpenAiTranscriber::class],
]);

it('turns voice calls on with OpenAI once it is connected, and off without it', function (): void {
    config(['chatvia.ai.voice' => 'auto']);

    expect(app(RealtimeCalls::class))->toBeInstanceOf(NullRealtimeCalls::class);

    config(['services.openai.api_key' => 'openai-key']);

    expect(app(RealtimeCalls::class))->toBeInstanceOf(OpenAiRealtimeCalls::class)
        ->and(app(RealtimeCalls::class)->isEnabled())->toBeTrue();
});

it('fails fast on an unknown driver name', function (string $key, string $contract): void {
    config(["chatvia.ai.{$key}" => 'llamaparse']);

    expect(fn (): object => app($contract))->toThrow(InvalidArgumentException::class, "chatvia.ai.{$key}");
})->with([
    ['document_parser', DocumentParser::class],
    ['reranker', Reranker::class],
    ['transcriber', Transcriber::class],
    ['voice', RealtimeCalls::class],
]);

it('fails fast when the embedding provider cannot return the size the database stores', function (string $provider, int $stored): void {
    config([
        'chatvia.ai.models.embedding' => $provider,
        'chatvia.embeddings.dimensions' => $stored,
    ]);

    expect(fn (): EmbeddingsProviderInterface => app(EmbeddingsProviderInterface::class))
        ->toThrow(EmbeddingDimensionsMismatchException::class, "returns 1024 dimensions, but the database stores {$stored}");
})->with([
    'mistral is fixed at 1024' => ['mistral', 1536],
    'jina stops at 1024' => ['jina', 2048],
    'cohere has fixed sizes' => ['cohere', 768],
    'openai small stops at 1536' => ['openai', 2048],
    'openai small on openrouter stops at 1536' => ['openrouter', 2048],
    'gemini starts at 128' => ['google', 64],
    'voyage has fixed sizes' => ['voyage', 1536],
]);

it('asks the embedding provider for the size the database stores', function (string $provider, string $class, int $stored): void {
    config([
        'chatvia.ai.models.embedding' => $provider,
        'chatvia.embeddings.dimensions' => $stored,
    ]);

    $embeddings = app(EmbeddingsProviderInterface::class);

    expect($embeddings->provider())->toBeInstanceOf($class)
        ->and($embeddings->getDimensions())->toBe($stored);
})->with([
    'cohere 1536' => ['cohere', Cohere::class, 1536],
    'jina 512' => ['jina', Jina::class, 512],
    'qwen 2048' => ['qwen', Qwen3::class, 2048],
    'mistral 1024' => ['mistral', MistralEmbeddings::class, 1024],
    'openai 1536' => ['openai', OpenAi::class, 1536],
    'openrouter 1536' => ['openrouter', OpenRouterEmbeddings::class, 1536],
    'gemini 768' => ['google', Gemini::class, 768],
    'voyage 2048' => ['voyage', Voyage::class, 2048],
]);

it('embeds nothing until the admin picks an embedding service', function (?string $provider): void {
    config(['chatvia.ai.models.embedding' => $provider]);

    $embeddings = app(EmbeddingsProviderInterface::class);

    expect($embeddings)->toBeInstanceOf(UnconfiguredEmbeddings::class)
        ->and($embeddings->getDimensions())->toBe((int) config('chatvia.embeddings.dimensions'))
        ->and(fn (): array => $embeddings->embed('hello'))->toThrow(EmbeddingServiceMissingException::class)
        ->and(fn (): array => $embeddings->embedQuery('hello'))->toThrow(EmbeddingServiceMissingException::class)
        ->and(fn (): array => $embeddings->embedBatch(['hello']))->toThrow(EmbeddingServiceMissingException::class);
})->with([
    'nothing saved' => [null],
    'an unknown value' => ['retired-service'],
]);
