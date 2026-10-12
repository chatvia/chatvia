<?php

use App\Admin\Pages\Setup;
use App\Admin\Settings\Actions\CompleteSetupAction;
use App\Admin\Settings\AiSettings;
use App\Admin\Settings\MailSettings;
use App\Admin\Settings\PlatformSettings;
use App\Identity\Workspace\Models\User;
use Domain\Llm\Enums\LlmProviderEnum;
use Domain\Llm\Models\Llm;
use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Services\ProviderAdapters;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * The admin setup of a new install: every panel page leads to it until it is
 * done or skipped, and it saves the AI provider and model, search and email
 * in one go ({@see CompleteSetupAction}). The provider's model list both
 * fills the model picker and checks the key.
 */
beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create()->fresh();
    $this->admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

    Filament::setCurrentPanel('admin');
    setupNotDone();
});

function setupNotDone(): void
{
    $platform = app(PlatformSettings::class);
    $platform->setup_completed_at = null;
    $platform->save();
}

function setupSetting(string $class, string $property): mixed
{
    app()->forgetScopedInstances();

    return app($class)->{$property};
}

function setupPage(User $admin): Testable
{
    return Livewire::actingAs($admin, 'admin')->test(Setup::class);
}

function fakeOpenRouterModels(): void
{
    Http::fake(['openrouter.ai/api/v1/models' => Http::response(['data' => [
        [
            'id' => 'meta-llama/llama-4-maverick',
            'name' => 'Llama 4 Maverick',
            'context_length' => 1_048_576,
            'pricing' => ['prompt' => '0.00000015', 'completion' => '0.0000006'],
            'architecture' => ['input_modalities' => ['text', 'image'], 'output_modalities' => ['text']],
            'supported_parameters' => ['tools', 'temperature'],
        ],
        [
            'id' => 'openai/text-embedding-3-small',
            'name' => 'Text embedding',
            'architecture' => ['input_modalities' => ['text'], 'output_modalities' => ['embeddings']],
        ],
    ]])]);
}

it('leads every panel page to the setup until it is done', function (): void {
    $this->actingAs($this->admin, 'admin');

    $this->get(Filament::getUrl())->assertRedirect(route('filament.admin.setup'));
    $this->get(route('filament.admin.auth.profile'))->assertOk();
    $this->get(route('filament.admin.setup'))->assertOk()->assertSee('Welcome to');

    $platform = app(PlatformSettings::class);
    $platform->setup_completed_at = now()->toIso8601String();
    $platform->save();

    $this->get(Filament::getUrl())->assertOk();
});

it('lists the provider\'s chat models once the key is typed', function (): void {
    fakeOpenRouterModels();

    setupPage($this->admin)
        ->fillForm(['chat_kind' => 'openrouter', 'chat_api_key' => 'sk-or-test'])
        ->call('loadModels')
        ->assertSet('models', ['meta-llama/llama-4-maverick' => 'Llama 4 Maverick (meta-llama/llama-4-maverick)'])
        ->assertFormFieldVisible('chat_model')
        ->assertFormFieldHidden('chat_model_id');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://openrouter.ai/api/v1/models'
        && $request->header('Authorization') === ['Bearer sk-or-test']);
});

it('saves the provider, its model, search and email, then opens the panel', function (): void {
    fakeOpenRouterModels();

    setupPage($this->admin)
        ->fillForm(['chat_kind' => 'openrouter', 'chat_api_key' => 'sk-or-test'])
        ->call('loadModels')
        ->fillForm([
            'chat_model' => 'meta-llama/llama-4-maverick',
            'embedding_provider' => 'cohere',
            'embedding_api_key' => 'co-test',
            'mailer' => 'smtp',
            'from_address' => 'chat@example.com',
            'smtp_host' => 'smtp.example.com',
            'smtp_port' => 587,
            'smtp_username' => 'chat',
            'smtp_password' => 'smtp-secret',
        ])
        ->call('complete')
        ->assertHasNoFormErrors()
        ->assertRedirect(Filament::getUrl());

    $provider = LlmProvider::query()->where('kind', LlmProviderEnum::OPENROUTER)->sole();
    $model = Llm::query()->where('llm_provider_id', $provider->id)->sole();

    expect($provider->api_key)->toBe('sk-or-test')
        ->and($model->internal_name)->toBe('meta-llama/llama-4-maverick')
        ->and($model->descriptive_name)->toBe('Llama 4 Maverick')
        ->and($model->context_window)->toBe(1_048_576)
        ->and((float) $model->input_price_per_1m_tokens)->toBe(0.15)
        ->and($model->features)->toBe(['vision' => true, 'function_calling' => true])
        ->and(setupSetting(AiSettings::class, 'default_agent_model'))->toBe($model->external_name)
        ->and(setupSetting(AiSettings::class, 'embedding_provider'))->toBe('cohere')
        ->and(setupSetting(AiSettings::class, 'cohere_api_key'))->toBe('co-test')
        ->and(setupSetting(MailSettings::class, 'mailer'))->toBe('smtp')
        ->and(setupSetting(MailSettings::class, 'smtp_password'))->toBe('smtp-secret')
        ->and(setupSetting(PlatformSettings::class, 'setup_completed_at'))->not->toBeNull();
});

it('stops on the first step when the provider refuses the key', function (): void {
    Http::fake(['openrouter.ai/*' => Http::response(['error' => ['message' => 'No auth credentials found']], 401)]);

    setupPage($this->admin)
        ->fillForm(['chat_kind' => 'openrouter', 'chat_api_key' => 'wrong-key', 'chat_model_id' => 'some/model'])
        ->goToNextWizardStep()
        ->assertHasErrors(['data.chat_api_key'])
        ->assertWizardCurrentStep(1)
        ->assertSee('refused the API key');

    expect(LlmProvider::query()->where('kind', LlmProviderEnum::OPENROUTER)->exists())->toBeFalse();
});

it('takes a typed model name from a server that lists none', function (): void {
    Http::fake(['ollama.test/*' => Http::response('Not found', 404)]);

    setupPage($this->admin)
        ->fillForm(['chat_kind' => 'custom', 'chat_base_url' => 'http://ollama.test:11434/v1'])
        ->call('loadModels')
        ->assertFormFieldVisible('chat_model_id')
        ->fillForm([
            'chat_model_id' => 'llama3.1:8b',
            'embedding_provider' => 'jina',
            'embedding_api_key' => 'jina-test',
        ])
        ->call('complete')
        ->assertHasNoFormErrors();

    $provider = LlmProvider::query()->where('kind', LlmProviderEnum::CUSTOM)->sole();

    expect(app(ProviderAdapters::class)->for(LlmProviderEnum::CUSTOM)->baseUrl($provider))->toBe('http://ollama.test:11434/v1')
        ->and($provider->api_key)->toBeNull()
        ->and($provider->llms()->sole()->internal_name)->toBe('llama3.1:8b');
});

it('reuses a Scaleway chat key for Qwen3 search', function (): void {
    Http::fake(['api.scaleway.ai/*' => Http::response(['data' => [['id' => 'qwen3-235b-a22b-instruct-2507']]])]);

    setupPage($this->admin)
        ->fillForm(['chat_kind' => 'scaleway', 'chat_api_key' => 'scw-test'])
        ->assertFormSet(['embedding_provider' => 'qwen'])
        ->call('loadModels')
        ->assertFormFieldHidden('embedding_api_key')
        ->fillForm(['chat_model' => 'qwen3-235b-a22b-instruct-2507'])
        ->call('complete')
        ->assertHasNoFormErrors();

    expect(setupSetting(AiSettings::class, 'scaleway_api_key'))->toBe('scw-test')
        ->and(setupSetting(AiSettings::class, 'embedding_provider'))->toBe('qwen');
});

it('keeps a Scaleway project address, for chat and for Scaleway search', function (): void {
    Http::fake(['api.scaleway.ai/*' => Http::response(['data' => [['id' => 'mistral-small-3.2-24b-instruct-2506']]])]);

    $page = setupPage($this->admin)
        ->fillForm(['chat_kind' => 'scaleway', 'chat_base_url' => 'https://api.scaleway.ai/project-x/v1', 'chat_api_key' => 'scw-test'])
        ->assertFormFieldVisible('chat_base_url')
        ->fillForm(['chat_model' => 'mistral-small-3.2-24b-instruct-2506']);

    $requests = count(Http::recorded());

    // Next checks the key with the list already loaded for what is typed.
    $page->goToNextWizardStep()->assertWizardCurrentStep(2);

    expect(count(Http::recorded()))->toBe($requests);

    $page->call('complete')->assertHasNoFormErrors();

    $provider = LlmProvider::query()->where('base_url', 'https://api.scaleway.ai/project-x/v1')->sole();
    $model = $provider->llms()->sole();

    expect($provider->base_url)->toBe('https://api.scaleway.ai/project-x/v1')
        ->and($model->context_window)->toBe(128_000)
        ->and($model->features)->toBe(['vision' => true, 'function_calling' => true])
        ->and(setupSetting(AiSettings::class, 'scaleway_endpoint'))->toBe('https://api.scaleway.ai/project-x/v1')
        ->and(setupSetting(AiSettings::class, 'scaleway_api_key'))->toBe('scw-test');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.scaleway.ai/project-x/v1/models');
});

it('can be skipped', function (): void {
    setupPage($this->admin)
        ->callAction('skip')
        ->assertRedirect(Filament::getUrl());

    expect(setupSetting(PlatformSettings::class, 'setup_completed_at'))->not->toBeNull();
});

it('asks for two-factor authentication before it takes any key', function (): void {
    $withoutMfa = User::factory()->admin()->create()->fresh();

    setupPage($withoutMfa)
        ->assertSee('Set up two-factor authentication first')
        ->assertDontSee('Finish setup');
});
