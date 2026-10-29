<?php

use App\Admin\Clusters\Settings\Pages\ManageAiProviderSettings;
use App\Admin\Settings\AiProviderSettings;
use Domain\Knowledge\Embedding\Contracts\Reranker;
use Domain\Llm\Actions\Catalog\AddLlmModelAction;
use Domain\Llm\Actions\Catalog\EnsureLlmProviderAction;
use Domain\Llm\Data\Catalog\RemoteModelData;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Services\LlmProviders;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Settings → AI providers: each provider's key is entered once and serves
 * everything the provider does here (its chat models, the reranker). A typed
 * key is checked before it is saved, and a refused key saves nothing.
 */
beforeEach(function (): void {
    $this->admin = adminUser();
    app()->forgetInstance(Reranker::class);

    foreach (AiProvider::cases() as $provider) {
        config([$provider->apiKeyConfig() => null]);
    }

    config(['chatvia.ai.reranker' => 'auto']);
});

it('stores each key once, and the chat models and the reranker use it', function (): void {
    Http::fake(['openrouter.ai/api/v1/key' => Http::response(['data' => ['label' => 'sk-or-v1-tes...']]), 'openrouter.ai/api/v1/models/user' => Http::response(['data' => [['id' => 'openai/gpt-oss-120b', 'architecture' => ['output_modalities' => ['text']]]]])]);

    adminPage(ManageAiProviderSettings::class, $this->admin)
        ->fillForm(['openrouter_api_key' => 'sk-or-test'])
        ->call('save')
        ->assertHasNoFormErrors();

    $model = app(AddLlmModelAction::class)->execute(
        app(EnsureLlmProviderAction::class)->execute(LlmProviderKind::OpenRouter),
        new RemoteModelData('openai/gpt-oss-120b', 'gpt-oss-120b'),
    );

    expect(savedSetting(AiProviderSettings::class, 'openrouter_api_key'))->toBe('sk-or-test')
        ->and(config('services.openrouter.api_key'))->toBe('sk-or-test')
        ->and($model->provider->api_key)->toBeNull()
        ->and($model->isAvailable())->toBeTrue()
        ->and(app(LlmProviders::class)->for($model)->getApiKey())->toBe('sk-or-test')
        ->and(app(Reranker::class)->isEnabled())->toBeTrue();
    Http::assertSent(fn (Request $request): bool => $request->header('Authorization') === ['Bearer sk-or-test']);
});

it('refuses a key the provider refuses, and saves nothing', function (): void {
    Http::fake(['api.scaleway.ai/*' => Http::response(['message' => 'Unauthorized'], 401)]);

    adminPage(ManageAiProviderSettings::class, $this->admin)
        ->fillForm(['scaleway_api_key' => 'wrong-key'])
        ->call('save')
        ->assertHasFormErrors(['scaleway_api_key']);

    expect(savedSetting(AiProviderSettings::class, 'scaleway_api_key'))->toBeNull();
});
