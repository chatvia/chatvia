<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Services\OfferedModels;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Services\ModelsDevCatalog;
use Illuminate\Support\Facades\Http;

/**
 * The models a chatter's picker lists: the agent's own first (the default),
 * named as models.dev names them (the provider's id when it is not listed),
 * with an absolute logo URL a widget on another site can load.
 */
beforeEach(function (): void {
    forgetModelsDevCatalog();
    Http::fake([ModelsDevCatalog::URL => Http::response(['providers' => [
        'openai' => ['models' => ['gpt-6-luna' => ['name' => 'GPT-6 Luna']]],
    ], 'models' => []])]);
    app(ModelsDevCatalog::class)->refresh();

    $this->agent = createAgent(User::factory()->create());
    config(['chatvia.ai.chatter_model_choice' => true]);
});

it('lists the offered models with their names and logos', function (): void {
    $own = testLlm('gpt-6-luna', LlmProviderKind::OpenAi);
    $other = testLlm('my-local-model');
    $this->agent->forceFill(['llm_id' => $own->id])->saveQuietly();
    $this->agent->setSetting('enabled', true, 'model_choice');
    $this->agent->setSetting('models', [$other->id], 'model_choice');

    $models = app(OfferedModels::class)->for($this->agent->fresh());

    expect($models)->toHaveCount(2)
        ->and($models[0]->id)->toBe($own->id)
        ->and($models[0]->name)->toBe('GPT-6 Luna')
        ->and($models[0]->is_default)->toBeTrue()
        ->and($models[0]->logo)->toBe(url('/model-logos/openai.svg'))
        ->and($models[1]->name)->toBe('my-local-model')
        ->and($models[1]->is_default)->toBeFalse()
        ->and($models[1]->logo)->toBeNull();
});

it('lists nothing while Multi-model is off', function (): void {
    testLlm('my-local-model');

    expect(app(OfferedModels::class)->for($this->agent->fresh()))->toBe([]);
});
