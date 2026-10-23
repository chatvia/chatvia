<?php

declare(strict_types=1);

use Domain\Llm\Actions\Catalog\AddLlmModelAction;
use Domain\Llm\Actions\Catalog\SyncLlmFactsAction;
use Domain\Llm\Data\Catalog\RemoteModelData;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Services\ModelsDevCatalog;
use Illuminate\Support\Facades\Http;

/**
 * Models get their capabilities from the models.dev catalog when they are
 * added and in the daily sync; the catalog is the reference for a model it
 * lists, and a model it does not list keeps what the admin set.
 */
beforeEach(function (): void {
    config(['chatvia.ai.models_dev.enabled' => true]);
    Http::fake([ModelsDevCatalog::URL => Http::response(['providers' => ['openai' => ['models' => [
        'gpt-6-luna' => ['reasoning' => true, 'temperature' => false, 'reasoning_options' => [['type' => 'effort', 'values' => ['none', 'low', 'high']]]],
    ]]], 'models' => []]), '*' => Http::response(['choices' => []])]);
});

it('gives a model the catalog\'s facts when it is added', function (): void {
    $provider = testLlm('other', LlmProviderKind::OpenAi)->provider;

    $llm = app(AddLlmModelAction::class)->execute($provider, new RemoteModelData('gpt-6-luna', 'GPT-6 Luna'));

    expect($llm->reasons())->toBeTrue()
        ->and($llm->facts()?->efforts)->toBe(['none', 'low', 'high'])
        ->and($llm->facts()?->takesTemperature)->toBeFalse();
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

it('keeps what the admin set for a model the catalog does not list', function (): void {
    $mine = testLlm('my-model', LlmProviderKind::OpenAi, ['capabilities' => ['reasoning' => true]]);

    app(SyncLlmFactsAction::class)->execute();

    expect($mine->fresh()->capabilities)->toBe(['reasoning' => true]);
});

it('syncs from the command line', function (): void {
    testLlm('gpt-6-luna', LlmProviderKind::OpenAi);

    $this->artisan('chatvia:llm:sync-facts')->expectsOutputToContain('Updated 1 models')->assertSuccessful();
});
