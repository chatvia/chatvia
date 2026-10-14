<?php

use App\Admin\Clusters\Settings\Pages\ManageAiModelSettings;
use App\Admin\Settings\Actions\RemoveLlmModelAction;
use App\Admin\Settings\AiSettings;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Actions\Settings\ReplaceAgentModelAction;
use Domain\Llm\Actions\Catalog\AddLlmModelAction;
use Domain\Llm\Data\Catalog\RemoteModelData;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Enums\LlmProviderEnum;
use Domain\Llm\Models\Llm;
use Domain\Llm\Models\LlmProvider;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * Settings → AI models: the chat models agents can use are added from a
 * connected provider's list and removed with their agents moved to another
 * model; a service whose provider is not connected cannot be picked.
 */
beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create()->fresh();

    foreach (AiProvider::cases() as $provider) {
        config([$provider->apiKeyConfig() => null]);
    }
});

function modelsPage(User $admin): Testable
{
    return Livewire::actingAs($admin, 'admin')->test(ManageAiModelSettings::class);
}

function aiSetting(string $property): mixed
{
    app()->forgetScopedInstances();

    return app(AiSettings::class)->{$property};
}

it('adds a model from a connected provider\'s list', function (): void {
    connectAiProviders(AiProvider::OpenRouter);
    Http::fake(['openrouter.ai/api/v1/models' => Http::response(['data' => [[
        'id' => 'meta-llama/llama-4-maverick',
        'name' => 'Llama 4 Maverick',
        'context_length' => 1_048_576,
        'architecture' => ['input_modalities' => ['text', 'image'], 'output_modalities' => ['text']],
        'supported_parameters' => ['tools'],
    ]]])]);

    modelsPage($this->admin)
        ->callAction(TestAction::make('addModel')->table(), data: ['source' => 'account:openrouter', 'model' => 'meta-llama/llama-4-maverick'])
        ->assertHasNoActionErrors()
        ->assertNotified('Llama 4 Maverick added');

    $model = Llm::query()->where('internal_name', 'meta-llama/llama-4-maverick')->sole();

    expect($model->provider->kind)->toBe(LlmProviderEnum::OPENROUTER)
        ->and($model->provider->api_key)->toBeNull()
        ->and($model->features)->toBe(['vision' => true, 'function_calling' => true])
        ->and($model->isAvailable())->toBeTrue();
});

it('offers only connected providers and servers of your own', function (): void {
    connectAiProviders(AiProvider::Groq);
    $server = LlmProvider::factory()->create(['name' => 'Office Ollama']);

    $sources = modelsPage($this->admin)
        ->mountAction(TestAction::make('addModel')->table())
        ->instance()
        ->getSchema('mountedActionSchema0')
        ->getFlatFields()['source']
        ->getOptions();

    expect($sources)->toBe(['account:groq' => 'Groq', "server:{$server->id}" => 'Office Ollama']);
});

it('takes a typed model name from a server that lists none', function (): void {
    $server = LlmProvider::factory()->create(['base_url' => 'https://ollama.office.test/v1']);
    Http::fake(['ollama.office.test/*' => Http::response('Not found', 404)]);

    modelsPage($this->admin)
        ->callAction(TestAction::make('addModel')->table(), data: ['source' => "server:{$server->id}", 'model_id' => 'llama3.1:8b'])
        ->assertHasNoActionErrors();

    expect($server->llms()->sole()->internal_name)->toBe('llama3.1:8b');
});

it('removes a model, moving its agents and the jobs that used it', function (): void {
    $old = testLlm('old-model');
    $new = testLlm('new-model');
    $agent = createAgent($this->admin, ['llm_id' => $old->id]);

    $settings = app(AiSettings::class);
    $settings->default_agent_model = $old->external_name;
    $settings->fast_model = $old->external_name;
    $settings->save();

    modelsPage($this->admin)
        ->callAction(TestAction::make('removeModel')->table($old), data: ['replacement' => $new->id])
        ->assertHasNoActionErrors();

    expect($agent->fresh()->llm_id)->toBe($new->id)
        ->and($old->fresh()->is_enabled)->toBeFalse()
        ->and(aiSetting('default_agent_model'))->toBe($new->external_name)
        ->and(aiSetting('fast_model'))->toBeNull();
});

it('keeps the last model agents use', function (): void {
    Llm::query()->update(['is_enabled' => false]);
    $only = testLlm('only-model');
    createAgent($this->admin, ['llm_id' => $only->id]);

    modelsPage($this->admin)->assertActionDisabled(TestAction::make('removeModel')->table($only));

    expect(fn () => app(RemoveLlmModelAction::class)->execute($only))->toThrow(InvalidArgumentException::class);
});

it('turns a removed model back on when it is added again', function (): void {
    $model = testLlm('again-model');
    app(RemoveLlmModelAction::class)->execute($model);

    expect($model->fresh()->is_enabled)->toBeFalse();

    connectAiProviders();
    app(AddLlmModelAction::class)->execute($model->provider, new RemoteModelData('again-model', 'Again'));

    expect($model->fresh()->is_enabled)->toBeTrue();
});

it('moves every agent of a model with one action', function (): void {
    $from = testLlm('from-model');
    $to = testLlm('to-model');
    $agents = [createAgent($this->admin, ['llm_id' => $from->id]), createAgent($this->admin, ['llm_id' => $from->id])];

    expect(app(ReplaceAgentModelAction::class)->execute($from, $to))->toBe(2)
        ->and(array_map(fn ($agent): ?int => $agent->fresh()->llm_id, $agents))->toBe([$to->id, $to->id]);
});

it('refuses a search service whose provider is not connected', function (): void {
    modelsPage($this->admin)
        ->fillForm(['embedding_provider' => 'cohere'])
        ->call('save')
        ->assertHasFormErrors(['embedding_provider' => 'Connect Cohere in AI providers first.']);
});

it('shows how many models agents can use on the overview, or a warning', function (): void {
    Llm::query()->update(['is_enabled' => false]);

    expect(app(ManageAiModelSettings::class)->overviewStatus())->toBe(['No models yet', 'warning']);

    testLlm('one-model');

    expect(app(ManageAiModelSettings::class)->overviewStatus())->toBe(['1 model for agents', 'success']);
});
