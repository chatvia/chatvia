<?php

use App\Admin\Clusters\Settings\Pages\ManageAiProviderSettings;
use App\Admin\Clusters\Settings\Pages\SettingsOverview;
use App\Admin\Settings\AiProviderSettings;
use Domain\Knowledge\Embedding\Contracts\Reranker;
use Domain\Llm\Actions\Catalog\AddLlmModelAction;
use Domain\Llm\Actions\Catalog\EnsureLlmProviderAction;
use Domain\Llm\Data\Catalog\RemoteModelData;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Models\Llm;
use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Services\LlmProviders;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Settings → AI providers: each provider's key is entered once and serves
 * everything the provider does here (its chat models, embeddings, the
 * reranker). A typed key is checked before it is saved. Servers of your own
 * are added, changed and removed in a table.
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
    Http::fake(['openrouter.ai/api/v1/key' => Http::response(['data' => ['label' => 'sk-or-v1-tes...']]), 'openrouter.ai/api/v1/models' => Http::response(['data' => [['id' => 'openai/gpt-oss-120b', 'architecture' => ['output_modalities' => ['text']]]]])]);

    adminPage(ManageAiProviderSettings::class, $this->admin)
        ->fillForm(['openrouter_api_key' => 'sk-or-test'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Settings saved');

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
        ->assertHasFormErrors(['scaleway_api_key' => 'Scaleway AI refused the API key. Check that you copied all of it.']);

    expect(savedSetting(AiProviderSettings::class, 'scaleway_api_key'))->toBeNull();
});

it('checks a provider without chat models with a test embedding', function (): void {
    Http::fake(['api.cohere.com/*' => Http::response(['message' => 'invalid api token'], 401)]);

    adminPage(ManageAiProviderSettings::class, $this->admin)
        ->fillForm(['cohere_api_key' => 'wrong-key'])
        ->call('save')
        ->assertHasFormErrors(['cohere_api_key']);
});

it('saves a key it cannot check, with a warning', function (): void {
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    adminPage(ManageAiProviderSettings::class, $this->admin)
        ->fillForm(['groq_api_key' => 'gsk-test'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Saved, but the key could not be checked');

    expect(savedSetting(AiProviderSettings::class, 'groq_api_key'))->toBe('gsk-test');
});

it('marks what each provider is used for', function (): void {
    connectAiProviders(AiProvider::Scaleway);
    config(['chatvia.ai.models.embedding' => 'qwen', 'chatvia.ai.transcriber' => 'auto']);

    adminPage(ManageAiProviderSettings::class, $this->admin)
        ->assertSeeHtml('<span class="cv-chip cv-chip--used" title="In use">embeddings</span>')
        ->assertSeeHtml('<span class="cv-chip cv-chip--used" title="In use">speech to text</span>')
        ->assertSeeHtml('<span class="cv-chip">reranking</span>');
});

it('says how many agents stop answering before a key is removed', function (): void {
    $settings = app(AiProviderSettings::class);
    $settings->scaleway_api_key = 'scw-key';
    $settings->save();

    $model = testLlm('gpt-oss-120b', LlmProviderKind::Scaleway);
    createAgent($this->admin, ['llm_id' => $model->id]);

    adminPage(ManageAiProviderSettings::class, $this->admin)
        ->mountAction(TestAction::make('forget_scaleway_api_key')->schemaComponent('scaleway_api_key', schema: 'form'))
        ->assertMountedActionModalSee('1 agent answer with Scaleway AI models and stop answering until a key is added again.')
        ->callMountedAction();

    expect(savedSetting(AiProviderSettings::class, 'scaleway_api_key'))->toBeNull();
});

it('adds, changes and removes a server of your own', function (): void {
    Http::fake(['llm.office.test/*' => Http::response(['data' => [['id' => 'llama3.1:8b']]])]);

    adminPage(ManageAiProviderSettings::class, $this->admin)
        ->callAction(TestAction::make('addServer')->table(), data: [
            'name' => 'Office Ollama',
            'base_url' => 'https://llm.office.test/v1/',
            'api_key' => 'local-key',
            'headers' => ['X-Team' => 'support'],
        ])
        ->assertHasNoActionErrors()
        ->assertNotified('Server added');

    $server = LlmProvider::query()->where('kind', LlmProviderKind::Custom)->where('name', 'Office Ollama')->sole();

    expect($server->base_url)->toBe('https://llm.office.test/v1')
        ->and($server->api_key)->toBe('local-key')
        ->and($server->headers)->toBe(['X-Team' => 'support']);

    adminPage(ManageAiProviderSettings::class, $this->admin)
        ->callAction(TestAction::make('editServer')->table($server), data: ['name' => 'Ollama', 'base_url' => 'https://llm.office.test/v1', 'api_key' => ''])
        ->assertHasNoActionErrors();

    expect($server->fresh()->name)->toBe('Ollama')
        ->and($server->fresh()->api_key)->toBe('local-key');

    $model = Llm::factory()->for($server, 'provider')->create(['name' => 'llama3.1:8b']);
    $other = testLlm('other-model');
    $agent = createAgent($this->admin, ['llm_id' => $model->id]);

    adminPage(ManageAiProviderSettings::class, $this->admin)
        ->callAction(TestAction::make('removeServer')->table($server), data: ['replacement' => $other->id])
        ->assertHasNoActionErrors();

    expect($server->fresh()->is_enabled)->toBeFalse()
        ->and($model->fresh()->is_enabled)->toBeFalse()
        ->and($agent->fresh()->llm_id)->toBe($other->id);
});

it('does not save a server it cannot reach', function (): void {
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    adminPage(ManageAiProviderSettings::class, $this->admin)
        ->callAction(TestAction::make('addServer')->table(), data: ['name' => 'Gone', 'base_url' => 'https://gone.test/v1'])
        ->assertNotified('The server was not saved');

    expect(LlmProvider::query()->where('name', 'Gone')->exists())->toBeFalse();
});

it('shows the connected providers on the overview, or a warning while there is none', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->get(SettingsOverview::getUrl())
        ->assertSeeInOrder(['AI providers', 'Not set up']);

    connectAiProviders(AiProvider::Scaleway, AiProvider::Cohere);

    expect(app(ManageAiProviderSettings::class)->overviewStatus())->toBe(['Scaleway AI and Cohere connected', 'success']);
});
