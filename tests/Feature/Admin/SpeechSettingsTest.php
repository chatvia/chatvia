<?php

use App\Admin\Clusters\Settings\Pages\ManageAiModelSettings;
use App\Admin\Settings\AiSettings;
use Domain\Llm\Enums\AiProvider;
use Filament\Forms\Components\Select;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;

/**
 * The Speech to text card of Settings → AI models: Scaleway AI Whisper,
 * OpenAI Whisper or Off. No "Automatic": the list shows the service in use,
 * marks each provider connected or not, and greys out the ones that are not.
 * A new pick checks the provider's key with a real call as it is saved, and
 * is refused when the call fails.
 */
beforeEach(function (): void {
    $this->admin = adminUser();

    config([
        'chatvia.ai.transcriber' => 'auto',
        AiProvider::Scaleway->apiKeyConfig() => null,
        AiProvider::OpenAi->apiKeyConfig() => null,
    ]);
});

function speechField(Testable $page): Select
{
    return $page->instance()->form->getFlatFields(withHidden: true)['transcriber'];
}

function savedTranscriber(): ?string
{
    app()->forgetScopedInstances();

    return app(AiSettings::class)->transcriber;
}

/** Whether a request went to Scaleway AI or OpenAI (the page lists OpenRouter's models too). */
function speechProviderCall(Request $request): bool
{
    return str_contains($request->url(), 'api.scaleway.ai') || str_contains($request->url(), 'api.openai.com');
}

it('shows the service in use, marked connected, with no Automatic', function (): void {
    connectAiProviders(AiProvider::Scaleway);

    $field = speechField(adminPage(ManageAiModelSettings::class, $this->admin)->assertFormSet(['transcriber' => 'scaleway']));

    expect($field->getOptions())->toBe([
        'scaleway' => 'Scaleway AI Whisper (connected)',
        'openai' => 'OpenAI Whisper (not connected)',
        'none' => 'Off',
    ])
        ->and($field->canSelectPlaceholder())->toBeFalse()
        ->and($field->isOptionDisabled('openai', 'OpenAI Whisper'))->toBeTrue()
        ->and($field->isOptionDisabled('scaleway', 'Scaleway AI Whisper'))->toBeFalse()
        ->and($field->isOptionDisabled('none', 'Off'))->toBeFalse();
});

it('shows Off when nothing is connected', function (): void {
    adminPage(ManageAiModelSettings::class, $this->admin)->assertFormSet(['transcriber' => 'none']);
});

it('checks the Scaleway AI key before it turns speech to text on', function (): void {
    connectAiProviders(AiProvider::Scaleway);
    config(['chatvia.ai.transcriber' => 'none']);
    Http::fake(['api.scaleway.ai/v1/models' => Http::response(['object' => 'list', 'data' => [['id' => 'whisper-large-v3', 'object' => 'model']]])]);

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->fillForm(['transcriber' => 'scaleway'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(savedTranscriber())->toBe('scaleway');

    // One call, though a save reads the form twice.
    expect(Http::recorded(speechProviderCall(...)))->toHaveCount(1);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.scaleway.ai/v1/models'
        && $request->hasHeader('Authorization', 'Bearer test-key'));
});

it('refuses OpenAI Whisper when OpenAI refuses the key', function (): void {
    connectAiProviders(AiProvider::OpenAi);
    config(['chatvia.ai.transcriber' => 'none']);
    Http::fake(['api.openai.com/v1/models' => Http::response(['error' => ['message' => 'Incorrect API key provided']], 401)]);

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->fillForm(['transcriber' => 'openai'])
        ->call('save')
        ->assertHasFormErrors(['transcriber' => 'OpenAI refused the API key. Check that you copied all of it.']);

    expect(savedTranscriber())->toBeNull();
});

it('refuses a service that is not connected', function (): void {
    Http::fake();

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->fillForm(['transcriber' => 'openai'])
        ->call('save')
        ->assertHasFormErrors(['transcriber' => 'OpenAI is not connected. Add its key in AI providers first.']);

    Http::assertNotSent(speechProviderCall(...));
});

it('does not call the provider again when it is unchanged', function (): void {
    connectAiProviders(AiProvider::Scaleway, AiProvider::OpenAi);
    config(['chatvia.ai.transcriber' => 'openai']);
    Http::fake();

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->assertFormSet(['transcriber' => 'openai'])
        ->call('save')
        ->assertHasNoFormErrors();

    Http::assertNotSent(speechProviderCall(...));
});

it('turns speech to text off without a call', function (): void {
    connectAiProviders(AiProvider::Scaleway);
    config(['chatvia.ai.transcriber' => 'scaleway']);
    Http::fake();

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->fillForm(['transcriber' => 'none'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(savedTranscriber())->toBe('none');
    Http::assertNotSent(speechProviderCall(...));
});
