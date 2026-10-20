<?php

use App\Admin\Clusters\Settings\Pages\ManageAiModelSettings;
use App\Admin\Settings\Actions\RemoveLlmModelAction;
use App\Admin\Settings\AiSettings;
use App\Admin\Settings\Jobs\ReindexEmbeddingsJob;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Actions\Settings\ReplaceAgentModelAction;
use Domain\Agent\Models\Agent;
use Domain\Integration\Tool\Jobs\ReembedToolVectorsJob;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswerQuestion;
use Domain\Llm\Actions\Catalog\AddLlmModelAction;
use Domain\Llm\Data\Catalog\RemoteModelData;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Models\Llm;
use Domain\Llm\Models\LlmProvider;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Testing\Fakes\PendingBatchFake;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * Settings → AI models: the chat models agents can use are added from a
 * connected provider's list and removed with their agents moved to another
 * model; a service whose provider is not connected cannot be picked. A new
 * embedding model, once documents are stored as vectors, is saved only after
 * a warning, and starts a re-index.
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

/** OpenRouter connected, listing two chat models. */
function modelsFakeOpenRouter(): void
{
    connectAiProviders(AiProvider::OpenRouter);
    Http::fake(['openrouter.ai/api/v1/key' => Http::response(['data' => ['label' => 'sk-or-v1-tes...']]), 'openrouter.ai/api/v1/models' => Http::response(['data' => [
        [
            'id' => 'meta-llama/llama-4-maverick',
            'name' => 'Llama 4 Maverick',
            'context_length' => 1_048_576,
            'architecture' => ['input_modalities' => ['text', 'image'], 'output_modalities' => ['text']],
            'supported_parameters' => ['tools'],
        ],
        [
            'id' => 'qwen/qwen3-235b-a22b',
            'name' => 'Qwen3 235B',
            'context_length' => 131_072,
            'architecture' => ['input_modalities' => ['text'], 'output_modalities' => ['text']],
            'supported_parameters' => ['tools'],
        ],
    ]])]);
}

it('uses the recommended model for quick tasks of the default model\'s provider', function (): void {
    Http::fake(['openrouter.ai/api/v1/key' => Http::response(['data' => ['label' => 'sk-or-v1-tes...']]), 'openrouter.ai/api/v1/models' => Http::response(['data' => [
        ['id' => 'openai/gpt-6-luna', 'name' => 'GPT-6 Luna', 'architecture' => ['input_modalities' => ['text', 'image']], 'supported_parameters' => ['tools']],
    ]])]);
    $chat = testLlm('meta-llama/llama-4-maverick', LlmProviderKind::OpenRouter);
    saveSettings(AiSettings::class, ['default_agent_model' => $chat->external_name, 'guardrails_model' => $chat->external_name]);
    // Saving settings reloads the saved keys; connect after it.
    connectAiProviders(AiProvider::OpenRouter);

    modelsPage($this->admin)
        ->callAction(TestAction::make('recommendedTaskModels')->schemaComponent('recommended-task-models', schema: 'form'))
        ->assertNotified('GPT-6 Luna does the quick tasks now')
        ->assertFormSet(['task_model' => 'openai/gpt-6-luna', 'guardrails_model' => null, 'eval_judge_model' => $chat->external_name, 'images_model' => 'openai/gpt-6-luna']);

    expect(savedSetting(AiSettings::class, 'task_model'))->toBe('openai/gpt-6-luna');
});

it('hides "Use the recommended models" for a server of your own', function (): void {
    $chat = testLlm('llama3.1:8b');
    saveSettings(AiSettings::class, ['default_agent_model' => $chat->external_name]);

    modelsPage($this->admin)
        ->assertSchemaComponentHidden('recommended-task-models', 'form');
});

it('adds a model from a connected provider\'s list', function (): void {
    modelsFakeOpenRouter();

    modelsPage($this->admin)
        ->callAction(TestAction::make('addModel')->table(), data: ['source' => 'account:openrouter', 'models' => ['meta-llama/llama-4-maverick']])
        ->assertHasNoActionErrors()
        ->assertNotified('Llama 4 Maverick added');

    $model = Llm::query()->where('internal_name', 'meta-llama/llama-4-maverick')->sole();

    expect($model->provider->kind)->toBe(LlmProviderKind::OpenRouter)
        ->and($model->provider->api_key)->toBeNull()
        ->and($model->features)->toBe(['vision' => true, 'function_calling' => true])
        ->and($model->isAvailable())->toBeTrue();
});

it('adds several models at once', function (): void {
    modelsFakeOpenRouter();

    modelsPage($this->admin)
        ->callAction(TestAction::make('addModel')->table(), data: ['source' => 'account:openrouter', 'models' => ['meta-llama/llama-4-maverick', 'qwen/qwen3-235b-a22b']])
        ->assertHasNoActionErrors()
        ->assertNotified('2 models added');

    expect(Llm::query()->whereIn('internal_name', ['meta-llama/llama-4-maverick', 'qwen/qwen3-235b-a22b'])->pluck('descriptive_name')->sort()->values()->all())
        ->toBe(['Llama 4 Maverick', 'Qwen3 235B']);
});

it('lists the first provider\'s models as soon as the form opens', function (): void {
    modelsFakeOpenRouter();

    modelsPage($this->admin)
        ->mountAction(TestAction::make('addModel')->table())
        ->assertSchemaStateSet(['source' => 'account:openrouter'], 'mountedActionSchema0')
        ->assertFormFieldVisible('models');
});

it('keeps Add off until a model is picked', function (): void {
    modelsFakeOpenRouter();

    $page = modelsPage($this->admin)->mountAction(TestAction::make('addModel')->table());

    expect($page->instance()->getMountedAction()->getModalSubmitAction()->isDisabled())->toBeTrue();

    $page->fillForm(['models' => ['qwen/qwen3-235b-a22b']]);

    expect($page->instance()->getMountedAction()->getModalSubmitAction()->isDisabled())->toBeFalse();
});

it('offers only connected providers and servers of your own', function (): void {
    connectAiProviders(AiProvider::Groq);
    Http::fake(['api.groq.com/*' => Http::response(['data' => []])]);
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

it('removes a model, moving its agents and the tasks that used it', function (): void {
    $old = testLlm('old-model');
    $new = testLlm('new-model');
    $agent = createAgent($this->admin, ['llm_id' => $old->id]);

    $settings = app(AiSettings::class);
    $settings->default_agent_model = $old->external_name;
    $settings->task_model = $old->external_name;
    $settings->titles_model = $old->external_name;
    $settings->images_model = $new->external_name;
    $settings->save();

    modelsPage($this->admin)
        ->callAction(TestAction::make('removeModel')->table($old), data: ['replacement' => $new->id])
        ->assertHasNoActionErrors();

    expect($agent->fresh()->llm_id)->toBe($new->id)
        ->and($old->fresh()->is_enabled)->toBeFalse()
        ->and(savedSetting(AiSettings::class, 'default_agent_model'))->toBe($new->external_name)
        ->and(savedSetting(AiSettings::class, 'task_model'))->toBeNull()
        ->and(savedSetting(AiSettings::class, 'titles_model'))->toBeNull()
        ->and(savedSetting(AiSettings::class, 'images_model'))->toBe($new->external_name);
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
        ->and(array_map(fn (Agent $agent): ?int => $agent->fresh()->llm_id, $agents))->toBe([$to->id, $to->id]);
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

it('warns and asks before it changes the embedding model of stored documents, then re-indexes', function (): void {
    connectAiProviders(AiProvider::OpenAi);
    // One trained chunk, so documents are stored as vectors.
    Chunk::factory()->create();
    $before = savedSetting(AiSettings::class, 'openai_embedding_model');
    Bus::fake();

    $page = modelsPage($this->admin)
        ->fillForm(['embedding_provider' => 'openai', 'openai_embedding_model' => 'text-embedding-3-large'])
        ->assertSee('Changing the embedding model means a re-index')
        ->call('save')
        ->assertActionMounted('changeEmbeddings')
        ->assertMountedActionModalSee(['Change the embedding model?', 'Saving starts this re-index in the background queue', 'Save and re-index']);

    expect(savedSetting(AiSettings::class, 'openai_embedding_model'))->toBe($before);
    Bus::assertNotDispatchedAfterResponse(ReindexEmbeddingsJob::class);

    $page->callMountedAction()->assertNotified('Settings saved. Re-index started');

    expect(savedSetting(AiSettings::class, 'embedding_provider'))->toBe('openai')
        ->and(savedSetting(AiSettings::class, 'openai_embedding_model'))->toBe('text-embedding-3-large');
    Bus::assertDispatchedAfterResponse(ReindexEmbeddingsJob::class);
});

it('changes the embedding model without asking while nothing is stored as vectors', function (): void {
    connectAiProviders(AiProvider::OpenAi);
    Chunk::query()->delete();
    AgentQuestionAnswerQuestion::query()->update(['embedding' => null]);

    modelsPage($this->admin)
        ->fillForm(['embedding_provider' => 'openai', 'openai_embedding_model' => 'text-embedding-3-large'])
        ->assertDontSee('Changing the embedding model means a re-index')
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertActionNotMounted('changeEmbeddings')
        ->assertNotified('Settings saved');

    expect(savedSetting(AiSettings::class, 'openai_embedding_model'))->toBe('text-embedding-3-large');
});

it('starts a re-index by hand and shows its progress', function (): void {
    // One trained chunk, so documents are stored as vectors.
    Chunk::factory()->create();
    Bus::fake();

    modelsPage($this->admin)
        ->callAction('reindex')
        ->assertNotified('Re-index started')
        ->assertSee('Re-indexing: 0% done');

    Bus::assertBatched(fn (PendingBatchFake $batch): bool => $batch->name === 'Embedding re-index');
    Bus::assertDispatched(ReembedToolVectorsJob::class);

    modelsPage($this->admin)->assertActionDisabled('reindex');
});
