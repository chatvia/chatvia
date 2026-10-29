<?php

declare(strict_types=1);

use Domain\Llm\Actions\Catalog\SyncLlmFactsAction;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Services\ModelsDevCatalog;
use Illuminate\Support\Facades\Http;

/**
 * The daily sync gives models their capabilities from the models.dev
 * catalog: the catalog is the reference for a model it lists, and a model it
 * does not list gets none from it.
 */
beforeEach(function (): void {
    forgetModelsDevCatalog();
    Http::fake([ModelsDevCatalog::URL => Http::response(['providers' => ['openai' => ['models' => [
        'gpt-6-luna' => ['reasoning' => true, 'temperature' => false, 'reasoning_options' => [['type' => 'effort', 'values' => ['none', 'low', 'high']]]],
    ]]], 'models' => []]), '*' => Http::response(['choices' => []])]);
});

it('fills in the facts of models added before the catalog knew them', function (): void {
    $luna = testLlm('gpt-6-luna', LlmProviderKind::OpenAi, ['capabilities' => ['reasoning' => false]]);
    $mine = testLlm('my-model', LlmProviderKind::OpenAi);

    $result = app(SyncLlmFactsAction::class)->execute();

    expect($result->updated)->toBe(1)
        ->and($result->notListed)->toBeGreaterThanOrEqual(1)
        ->and($luna->fresh()->reasons())->toBeTrue()
        ->and($luna->fresh()->facts()?->efforts)->toBe(['none', 'low', 'high'])
        ->and($mine->fresh()->facts()?->efforts)->toBeNull();
});
