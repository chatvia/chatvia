<?php

declare(strict_types=1);

use Domain\Llm\Actions\Catalog\AddLlmModelAction;
use Domain\Llm\Data\Catalog\RemoteModelData;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Services\ModelsDevCatalog;
use Illuminate\Support\Facades\Http;

/**
 * Adding a listed model to the catalog: it is kept under its provider's id,
 * once per provider, and takes its facts from the models.dev catalog.
 */
it('adds a model under its provider\'s id, once per provider', function (): void {
    $first = LlmProvider::factory()->create();
    $second = LlmProvider::factory()->create();
    $add = app(AddLlmModelAction::class);

    $one = $add->execute($first, new RemoteModelData('shared-model', 'Shared model'));
    $two = $add->execute($second, new RemoteModelData('shared-model', 'Shared model'));

    expect($one->name)->toBe('shared-model')
        ->and($two->name)->toBe('shared-model')
        ->and($two->is($one))->toBeFalse()
        ->and($add->execute($first, new RemoteModelData('shared-model', 'Renamed'))->is($one))->toBeTrue();
});

it('gives a model the catalog\'s facts when it is added', function (): void {
    config(['chatvia.ai.models_dev.enabled' => true]);
    Http::fake([ModelsDevCatalog::URL => Http::response(['providers' => ['openai' => ['models' => [
        'gpt-6-luna' => ['reasoning' => true, 'temperature' => false, 'reasoning_options' => [['type' => 'effort', 'values' => ['none', 'low', 'high']]]],
    ]]], 'models' => []])]);
    $provider = testLlm('other', LlmProviderKind::OpenAi)->provider;

    $llm = app(AddLlmModelAction::class)->execute($provider, new RemoteModelData('gpt-6-luna', 'GPT-6 Luna'));

    expect($llm->reasons())->toBeTrue()
        ->and($llm->facts()?->efforts)->toBe(['none', 'low', 'high'])
        ->and($llm->facts()?->takesTemperature)->toBeFalse();
});
