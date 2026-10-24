<?php

use Domain\Knowledge\Embedding\Rerank\RerankModelCatalog;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

/**
 * The reranker models the app knows: the relevance bars measured for each
 * (the generic bars for a model never measured, one model under two names
 * sharing its bars), and the OpenRouter rerankers offered in the picker, the
 * curated ones first.
 */
it('gives each model its thresholds, and unmeasured ones the generic bars', function (): void {
    $generic = RerankModelCatalog::thresholds('someone/new-reranker');

    expect($generic->knowledge)->toBe(0.30)
        ->and($generic->toolRead)->toBe(0.30)
        ->and($generic->toolWrite)->toBe(0.40)
        ->and(RerankModelCatalog::thresholds('rerank-v4.0-pro'))->toEqual(RerankModelCatalog::thresholds('cohere/rerank-4-pro'));
});

it('lists the curated OpenRouter rerankers first, then the live ones', function (): void {
    // A fresh client: TestCase fakes this list as empty for every other test.
    Http::swap(new Factory(app('events')));
    Http::fake(['openrouter.ai/api/v1/models?output_modalities=rerank*' => Http::response(['data' => [
        ['id' => 'cohere/rerank-4-pro', 'name' => 'Cohere: Rerank 4 Pro'],
        ['id' => 'brand/new-reranker', 'name' => 'Brand New Reranker'],
    ]])]);
    cache()->forget('rerank:openrouter-models');

    $options = app(RerankModelCatalog::class)->openRouterOptions();

    expect(array_key_first($options))->toBe('cohere/rerank-4-pro')
        ->and($options['cohere/rerank-4-pro'])->toStartWith('Cohere Rerank 4 Pro')
        ->and(array_key_last($options))->toBe('brand/new-reranker');
});
