<?php

use App\Admin\Clusters\Settings\Pages\ManageAiModelSettings;
use App\Admin\Forms\ModelPicker;
use App\Admin\Settings\Actions\RemoveLlmModelAction;
use App\Admin\Settings\AiSettings;
use App\Admin\Settings\Jobs\ReindexEmbeddingsJob;
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
use Domain\Llm\Services\ModelsDevCatalog;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Testing\Fakes\PendingBatchFake;
use Livewire\Livewire;

/**
 * Settings → AI models: the chat models agents can use are added from a
 * connected provider's list and removed with their agents moved to another
 * model; a service whose provider is not connected cannot be picked. A new
 * embedding model, once documents are stored as vectors, is saved only after
 * a warning, and starts a re-index.
 */
beforeEach(function (): void {
    $this->admin = adminUser();

    foreach (AiProvider::cases() as $provider) {
        config([$provider->apiKeyConfig() => null]);
    }
});

/** OpenRouter connected, listing two chat models. */
function modelsFakeOpenRouter(): void
{
    connectAiProviders(AiProvider::OpenRouter);
    Http::fake(['openrouter.ai/api/v1/key' => Http::response(['data' => ['label' => 'sk-or-v1-tes...']]), 'openrouter.ai/api/v1/models/user' => Http::response(['data' => [
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
    Http::fake(['openrouter.ai/api/v1/key' => Http::response(['data' => ['label' => 'sk-or-v1-tes...']]), 'openrouter.ai/api/v1/models/user' => Http::response(['data' => [
        ['id' => 'openai/gpt-6-luna', 'name' => 'GPT-6 Luna', 'architecture' => ['input_modalities' => ['text', 'image']], 'supported_parameters' => ['tools']],
    ]])]);
    $chat = testLlm('meta-llama/llama-4-maverick', LlmProviderKind::OpenRouter);
    saveSettings(AiSettings::class, ['default_agent_model' => (string) $chat->id, 'guardrails_model' => (string) $chat->id]);
    // Saving settings reloads the saved keys; connect after it.
    connectAiProviders(AiProvider::OpenRouter);

    $page = adminPage(ManageAiModelSettings::class, $this->admin)
        ->callAction(TestAction::make('recommendedTaskModels')->schemaComponent('recommended-task-models', schema: 'form'))
        ->assertNotified('openai/gpt-6-luna does the quick tasks now');
    $mini = (string) Llm::query()->where('name', 'openai/gpt-6-luna')->value('id');

    $page->assertFormSet(['task_model' => $mini, 'guardrails_model' => null, 'eval_judge_model' => (string) $chat->id, 'images_model' => $mini]);

    expect(savedSetting(AiSettings::class, 'task_model'))->toBe($mini);
});

it('hides "Use the recommended models" for a server of your own', function (): void {
    $chat = testLlm('llama3.1:8b');
    saveSettings(AiSettings::class, ['default_agent_model' => (string) $chat->id]);

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->assertSchemaComponentHidden('recommended-task-models', 'form');
});

it('adds a model from a connected provider\'s list', function (): void {
    modelsFakeOpenRouter();

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->callAction(TestAction::make('addModel')->table(), data: ['source' => 'account:openrouter', 'models' => ['meta-llama/llama-4-maverick']])
        ->assertHasNoActionErrors()
        ->assertNotified('meta-llama/llama-4-maverick added');

    $model = Llm::query()->where('name', 'meta-llama/llama-4-maverick')->sole();

    expect($model->provider->kind)->toBe(LlmProviderKind::OpenRouter)
        ->and($model->provider->api_key)->toBeNull()
        ->and($model->reasons())->toBeFalse()
        ->and($model->isAvailable())->toBeTrue();
});

it('marks a model as reasoning when its provider says so', function (): void {
    connectAiProviders(AiProvider::OpenRouter);
    Http::fake(['openrouter.ai/api/v1/key' => Http::response(['data' => ['label' => 'sk-or-v1-tes...']]), 'openrouter.ai/api/v1/models/user' => Http::response(['data' => [[
        'id' => 'deepseek/deepseek-r1',
        'name' => 'DeepSeek R1',
        'supported_parameters' => ['tools', 'reasoning'],
    ]]])]);

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->callAction(TestAction::make('addModel')->table(), data: ['source' => 'account:openrouter', 'models' => ['deepseek/deepseek-r1']])
        ->assertHasNoActionErrors();

    expect(Llm::query()->where('name', 'deepseek/deepseek-r1')->sole()->reasons())->toBeTrue();
});

it('lets the admin mark a model models.dev does not list as reasoning, and let agents reason', function (): void {
    $model = Llm::factory()->create(['capabilities' => ['reasoning' => false]]);

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->assertTableColumnExists('reasons')
        ->call('updateTableColumnState', 'reasons', (string) $model->getKey(), true)
        ->fillForm(['allow_reasoning' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($model->refresh()->reasons())->toBeTrue()
        ->and(savedSetting(AiSettings::class, 'allow_reasoning'))->toBeTrue();
});

it('adds several models at once', function (): void {
    modelsFakeOpenRouter();

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->callAction(TestAction::make('addModel')->table(), data: ['source' => 'account:openrouter', 'models' => ['meta-llama/llama-4-maverick', 'qwen/qwen3-235b-a22b']])
        ->assertHasNoActionErrors()
        ->assertNotified('2 models added');

    expect(Llm::query()->whereIn('name', ['meta-llama/llama-4-maverick', 'qwen/qwen3-235b-a22b'])->pluck('name')->sort()->values()->all())
        ->toBe(['meta-llama/llama-4-maverick', 'qwen/qwen3-235b-a22b']);
});

it('warns about a model that cannot call tools while adding it, still adds it, and marks it in the list', function (): void {
    config(['chatvia.ai.models_dev.enabled' => true]);
    Http::fake([ModelsDevCatalog::URL => Http::response(['providers' => ['openrouter' => ['models' => [
        'qwen/qwen3-235b-a22b' => ['tool_call' => false],
        'meta-llama/llama-4-maverick' => ['tool_call' => true],
    ]]], 'models' => []])]);
    modelsFakeOpenRouter();

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->mountAction(TestAction::make('addModel')->table())
        ->fillForm(['models' => ['meta-llama/llama-4-maverick']])
        ->assertMountedActionModalDontSee('cannot call tools')
        ->fillForm(['models' => ['meta-llama/llama-4-maverick', 'qwen/qwen3-235b-a22b']])
        ->assertMountedActionModalSee(['qwen/qwen3-235b-a22b cannot call tools', 'Tools will not work for agents that use it'])
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertNotified('2 models added')
        ->assertSee('No tool calling: tools will not work');

    expect(Llm::query()->where('name', 'qwen/qwen3-235b-a22b')->sole()->callsTools())->toBeFalse();
});

it('lists only the models that read images for image descriptions, and keeps the one in use', function (): void {
    $reader = testLlm('reader', attributes: ['capabilities' => ['modalities' => ['input' => ['text', 'image']]]]);
    $textOnly = testLlm('text-only', attributes: ['capabilities' => ['modalities' => ['input' => ['text']]]]);
    $unknown = testLlm('unknown');

    $options = fn (): array => array_replace(...array_values(adminPage(ManageAiModelSettings::class, $this->admin)
        ->instance()->form->getFlatFields(withHidden: true)['images_model']->getOptions()));

    expect(array_keys($options()))->toBe([$reader->id]);

    $settings = app(AiSettings::class);
    $settings->images_model = (string) $textOnly->id;
    $settings->save();

    expect(array_keys($options()))->toEqualCanonicalizing([$reader->id, $textOnly->id])
        ->and($options())->not->toHaveKey($unknown->id);
});

it('lists the first provider\'s models as soon as the form opens', function (): void {
    modelsFakeOpenRouter();

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->mountAction(TestAction::make('addModel')->table())
        ->assertSchemaStateSet(['source' => 'account:openrouter'], 'mountedActionSchema0')
        ->assertFormFieldVisible('models');
});

it('keeps Add off until a model is picked', function (): void {
    modelsFakeOpenRouter();

    $page = adminPage(ManageAiModelSettings::class, $this->admin)->mountAction(TestAction::make('addModel')->table());

    expect($page->instance()->getMountedAction()->getModalSubmitAction()->isDisabled())->toBeTrue();

    $page->fillForm(['models' => ['qwen/qwen3-235b-a22b']]);

    expect($page->instance()->getMountedAction()->getModalSubmitAction()->isDisabled())->toBeFalse();
});

it('shows every chat provider and the servers of your own, the ones without a key greyed out', function (): void {
    config(['chatvia.ai.models_dev.enabled' => true]);
    connectAiProviders(AiProvider::Groq);
    Http::fake(['api.groq.com/*' => Http::response(['data' => []])]);
    $server = LlmProvider::factory()->create(['name' => 'Office Ollama']);

    $page = adminPage(ManageAiModelSettings::class, $this->admin)->mountAction(TestAction::make('addModel')->table());
    $field = $page->instance()->getSchema('mountedActionSchema0')->getFlatFields()['source'];

    expect($field->getOptions())->toHaveKeys(['account:openrouter', 'account:groq', "server:{$server->id}"])
        ->and($field->getOptions())->not->toHaveKeys(['account:cohere', 'account:azure'])
        ->and($field->getOptions()['account:openrouter'])->toContain('OpenRouter (no key yet)')
        ->and($field->getOptions()['account:groq'])->toContain('/admin/model-logos/groq.svg')->not->toContain('no key yet')
        ->and($field->getOptions()["server:{$server->id}"])->toBe('Office Ollama')
        ->and($field->isOptionDisabled('account:openrouter', ''))->toBeTrue()
        ->and($field->isOptionDisabled('account:groq', ''))->toBeFalse()
        ->and($field->isOptionDisabled("server:{$server->id}", ''))->toBeFalse();

    $page->assertSchemaStateSet(['source' => 'account:groq'], 'mountedActionSchema0');
});

it('lists the models to tick, with their ids, and marks the ones agents use already', function (): void {
    modelsFakeOpenRouter();
    $page = adminPage(ManageAiModelSettings::class, $this->admin)->callAction(TestAction::make('addModel')->table(), data: ['source' => 'account:openrouter', 'models' => ['qwen/qwen3-235b-a22b']]);

    $field = $page->mountAction(TestAction::make('addModel')->table())->instance()->getSchema('mountedActionSchema0')->getFlatFields()['models'];

    expect($field)->toBeInstanceOf(ModelPicker::class)
        ->and($field->isOptionDisabled('qwen/qwen3-235b-a22b', ''))->toBeTrue()
        ->and($field->isOptionDisabled('meta-llama/llama-4-maverick', ''))->toBeFalse()
        ->and($field->getDescriptions()['qwen/qwen3-235b-a22b'] ?? '')->toContain('Agents can use it already');
});

it('takes a typed model name from a server that lists none', function (): void {
    $server = LlmProvider::factory()->create(['base_url' => 'https://ollama.office.test/v1']);
    Http::fake(['ollama.office.test/*' => Http::response('Not found', 404)]);

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->callAction(TestAction::make('addModel')->table(), data: ['source' => "server:{$server->id}", 'model_id' => 'llama3.1:8b'])
        ->assertHasNoActionErrors();

    expect($server->llms()->sole()->name)->toBe('llama3.1:8b');
});

it('removes a model, moving its agents and the tasks that used it', function (): void {
    $old = testLlm('old-model');
    $new = testLlm('new-model');
    $agent = createAgent($this->admin, ['llm_id' => $old->id]);

    $settings = app(AiSettings::class);
    $settings->default_agent_model = (string) $old->id;
    $settings->task_model = (string) $old->id;
    $settings->titles_model = (string) $old->id;
    $settings->images_model = (string) $new->id;
    $settings->save();

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->callAction(TestAction::make('removeModel')->table($old), data: ['replacement' => $new->id])
        ->assertHasNoActionErrors();

    expect($agent->fresh()->llm_id)->toBe($new->id)
        ->and($old->fresh()->is_enabled)->toBeFalse()
        ->and(savedSetting(AiSettings::class, 'default_agent_model'))->toBe((string) $new->id)
        ->and(savedSetting(AiSettings::class, 'task_model'))->toBeNull()
        ->and(savedSetting(AiSettings::class, 'titles_model'))->toBeNull()
        ->and(savedSetting(AiSettings::class, 'images_model'))->toBe((string) $new->id);
});

it('keeps the last model agents use', function (): void {
    Llm::query()->update(['is_enabled' => false]);
    $only = testLlm('only-model');
    createAgent($this->admin, ['llm_id' => $only->id]);

    adminPage(ManageAiModelSettings::class, $this->admin)->assertActionDisabled(TestAction::make('removeModel')->table($only));

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
    adminPage(ManageAiModelSettings::class, $this->admin)
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
    // The re-index is queued by a deferred callback, which Livewire's test
    // requests (run without middleware) never invoke: run each one at once.
    $this->app->bind(DeferredCallbackCollection::class, fn (): DeferredCallbackCollection => new class extends DeferredCallbackCollection
    {
        public function offsetSet(mixed $offset, mixed $value): void
        {
            $value();
        }
    });

    $page = adminPage(ManageAiModelSettings::class, $this->admin)
        ->fillForm(['embedding_provider' => 'openai', 'openai_embedding_model' => 'text-embedding-3-large'])
        ->assertSee('Changing the embedding model means a re-index')
        ->call('save')
        ->assertActionMounted('changeEmbeddings')
        ->assertMountedActionModalSee(['Change the embedding model?', 'Saving starts this re-index in the background queue', 'Save and re-index']);

    expect(savedSetting(AiSettings::class, 'openai_embedding_model'))->toBe($before);
    Bus::assertNotDispatched(ReindexEmbeddingsJob::class);

    $page->callMountedAction()->assertNotified('Settings saved. Re-index started');

    expect(savedSetting(AiSettings::class, 'embedding_provider'))->toBe('openai')
        ->and(savedSetting(AiSettings::class, 'openai_embedding_model'))->toBe('text-embedding-3-large');

    Bus::assertDispatched(ReindexEmbeddingsJob::class);
});

it('changes the embedding model without asking while nothing is stored as vectors', function (): void {
    connectAiProviders(AiProvider::OpenAi);
    Chunk::query()->delete();
    AgentQuestionAnswerQuestion::query()->update(['embedding' => null]);

    adminPage(ManageAiModelSettings::class, $this->admin)
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

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->callAction('reindex')
        ->assertNotified('Re-index started')
        ->assertSee('Re-indexing: 0% done');

    Bus::assertBatched(fn (PendingBatchFake $batch): bool => $batch->name === 'Embedding re-index');
    Bus::assertDispatched(ReembedToolVectorsJob::class);

    adminPage(ManageAiModelSettings::class, $this->admin)->assertActionDisabled('reindex');
});
