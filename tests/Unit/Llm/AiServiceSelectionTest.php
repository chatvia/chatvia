<?php

use Domain\Knowledge\Embedding\Cohere;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Domain\Knowledge\Embedding\Contracts\Reranker;
use Domain\Knowledge\Embedding\Exceptions\EmbeddingDimensionsMismatchException;
use Domain\Knowledge\Embedding\Jina;
use Domain\Knowledge\Embedding\Mistral as MistralEmbeddings;
use Domain\Knowledge\Embedding\Qwen3;
use Domain\Knowledge\Embedding\Rerank\CohereReranker;
use Domain\Knowledge\Embedding\Rerank\NullReranker;
use Domain\Knowledge\Embedding\Rerank\Qwen3Reranker;
use Domain\Knowledge\Ingestion\Contracts\DocumentParser;
use Domain\Knowledge\Ingestion\Contracts\TextSplitter;
use Domain\Knowledge\Ingestion\Services\Chunking\ChunkFitTextSplitter;
use Domain\Knowledge\Ingestion\Services\Chunking\LocalTextSplitter;
use Domain\Knowledge\Ingestion\Services\Parsing\LocalDocumentParser;
use Domain\Knowledge\Ingestion\Services\Parsing\MistralDocumentParser;
use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Llm\Contracts\Transcriber;
use Domain\Llm\Enums\LlmProviderEnum;
use Domain\Llm\Operations\Media\NullTranscriber;
use Domain\Llm\Operations\Media\ScalewayTranscriber;
use Domain\Llm\Providers\Groq;
use Domain\Llm\Providers\Mistral;
use Domain\Llm\Providers\OpenRouter;
use Domain\Llm\Providers\Scaleway;
use Domain\Llm\Services\DocumentLlm;

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
        'services.chunkfit.api_key' => null,
        'services.scalewayai.api_key' => null,
        'services.cohere.api_key' => null,
        'chatvia.ai.document_parser' => 'auto',
        'chatvia.ai.text_splitter' => 'auto',
        'chatvia.ai.reranker' => 'auto',
        'chatvia.ai.transcriber' => 'auto',
        'chatvia.ai.document_llm' => 'auto',
        'chatvia.ai.models.embedding' => 'qwen',
    ]);
});

it('uses only local or "off" services when no vendor key is set', function (): void {
    expect(app(DocumentParser::class))->toBeInstanceOf(LocalDocumentParser::class)
        ->and(app(TextSplitter::class))->toBeInstanceOf(LocalTextSplitter::class)
        ->and(app(Transcriber::class))->toBeInstanceOf(NullTranscriber::class)
        ->and(app(DocumentLlm::class)->isAvailable())->toBeFalse();
});

it('keeps the hosted services when their keys are set, as in production', function (): void {
    config([
        'services.mistral.api_key' => 'mistral-key',
        'services.chunkfit.api_key' => 'chunkfit-key',
        'services.scalewayai.api_key' => 'scaleway-key',
        'chatvia.ai.provider' => 'scaleway',
    ]);

    $documentLlm = app(DocumentLlm::class);

    expect(app(DocumentParser::class))->toBeInstanceOf(MistralDocumentParser::class)
        ->and(app(DocumentParser::class)->name())->toBe('mistral-ocr')
        ->and(app(TextSplitter::class))->toBeInstanceOf(ChunkFitTextSplitter::class)
        ->and(app(TextSplitter::class)->name())->toBe('chunkfit')
        ->and(app(Transcriber::class))->toBeInstanceOf(ScalewayTranscriber::class)
        ->and(app(Reranker::class))->toBeInstanceOf(Qwen3Reranker::class)
        ->and(app(EmbeddingsProviderInterface::class))->toBeInstanceOf(Qwen3::class)
        ->and(app(LlmProviderInterface::class))->toBeInstanceOf(Scaleway::class)
        ->and($documentLlm->isAvailable())->toBeTrue()
        ->and($documentLlm->provider())->toBeInstanceOf(Mistral::class)
        ->and($documentLlm->slug())->toBe('mistral');
});

it('uses a self-hosted chunker without an API key', function (): void {
    config(['services.chunkfit.base_url' => 'http://chunker:8000/api/v1']);

    expect(app(TextSplitter::class))->toBeInstanceOf(ChunkFitTextSplitter::class);
});

it('lets an explicit driver override auto', function (): void {
    config([
        'services.mistral.api_key' => 'mistral-key',
        'services.chunkfit.api_key' => 'chunkfit-key',
        'services.scalewayai.api_key' => 'scaleway-key',
        'chatvia.ai.document_parser' => 'local',
        'chatvia.ai.text_splitter' => 'local',
        'chatvia.ai.transcriber' => 'none',
        'chatvia.ai.reranker' => 'none',
        'chatvia.ai.document_llm' => 'none',
    ]);

    expect(app(DocumentParser::class))->toBeInstanceOf(LocalDocumentParser::class)
        ->and(app(TextSplitter::class))->toBeInstanceOf(LocalTextSplitter::class)
        ->and(app(Transcriber::class))->toBeInstanceOf(NullTranscriber::class)
        ->and(app(Reranker::class))->toBeInstanceOf(NullReranker::class)
        ->and(app(DocumentLlm::class)->isAvailable())->toBeFalse();
});

it('follows the embedding provider for the auto reranker', function (string $embedding, string $reranker): void {
    config(['chatvia.ai.models.embedding' => $embedding]);

    expect(app(Reranker::class))->toBeInstanceOf($reranker);
})->with([
    'qwen' => ['qwen', Qwen3Reranker::class],
    'cohere' => ['cohere', CohereReranker::class],
    'jina' => ['jina', NullReranker::class],
    'mistral' => ['mistral', NullReranker::class],
]);

it('fails fast on an unknown driver name', function (string $key, string $contract): void {
    config(["chatvia.ai.{$key}" => 'llamaparse']);

    expect(fn () => app($contract))->toThrow(InvalidArgumentException::class, "chatvia.ai.{$key}");
})->with([
    ['document_parser', DocumentParser::class],
    ['text_splitter', TextSplitter::class],
    ['reranker', Reranker::class],
    ['transcriber', Transcriber::class],
    ['document_llm', DocumentLlm::class],
]);

it('refuses a document LLM provider that cannot read PDF documents', function (): void {
    config(['chatvia.ai.document_llm' => 'scaleway']);

    expect(fn () => app(DocumentLlm::class))->toThrow(InvalidArgumentException::class, 'Use auto, mistral, none');
});

it('maps every chat provider slug to its client and falls back to OpenRouter', function (?string $slug, string $class): void {
    config(['chatvia.ai.provider' => $slug]);

    expect(app(LlmProviderInterface::class))->toBeInstanceOf($class);
})->with([
    'scaleway' => ['scaleway', Scaleway::class],
    'mistral' => ['mistral', Mistral::class],
    'groq' => ['groq', Groq::class],
    'openrouter' => ['openrouter', OpenRouter::class],
    'no client of its own' => ['openai', OpenRouter::class],
    'unknown' => ['something-else', OpenRouter::class],
    'unset' => [null, OpenRouter::class],
]);

it('uses one provider vocabulary for chat routing and the LLM catalog', function (): void {
    $routable = array_filter(LlmProviderEnum::cases(), fn (LlmProviderEnum $p): bool => $p->providerClass() !== null);

    expect(array_map(fn (LlmProviderEnum $p): string => $p->value, array_values($routable)))
        ->toBe(['scaleway', 'mistral', 'openrouter', 'groq'])
        ->and(LlmProviderEnum::MISTRAL->readsDocuments())->toBeTrue()
        ->and(LlmProviderEnum::SCALEWAY->readsDocuments())->toBeFalse();
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
]);
