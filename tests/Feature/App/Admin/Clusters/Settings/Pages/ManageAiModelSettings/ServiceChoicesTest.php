<?php

use App\Admin\Clusters\Settings\Pages\ManageAiModelSettings;
use App\Admin\Settings\AiSettings;
use Domain\Knowledge\Embedding\Contracts\Reranker;
use Domain\Llm\Enums\AiProvider;
use Filament\Forms\Components\Select;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;

/**
 * The service pickers of Settings → AI models (reranker, document reading,
 * summaries and PDF chat, speech to text, voice) share one behaviour. There
 * is no "Automatic": each list shows the service in use, marks each provider
 * connected or not, and greys out the ones that are not. A new pick is
 * checked with one real call as it is saved; Off, or an unchanged pick,
 * needs none. Each service's own call and error messages are in its card's
 * test next to this one.
 */
beforeEach(function (): void {
    $this->admin = adminUser();
    app()->forgetInstance(Reranker::class);

    foreach (AiProvider::cases() as $provider) {
        config([$provider->apiKeyConfig() => null]);
    }

    config(['chatvia.ai.reranker' => 'auto', 'chatvia.ai.document_parser' => 'auto', 'chatvia.ai.document_llm' => 'auto', 'chatvia.ai.transcriber' => 'auto', 'chatvia.ai.voice' => 'auto']);
});

function scField(Testable $page, string $property): Select
{
    return $page->instance()->form->getFlatFields(withHidden: true)[$property];
}

/** A call to an AI service: anything but the model lists the page reads to show its choices. */
function scServiceCall(Request $request): bool
{
    return ! Str::contains($request->url(), ['openrouter.ai/api/v1/models/user', 'openrouter.ai/api/v1/embeddings/models', 'models.dev/']);
}

/** Each picker, with the provider a test connects. */
dataset('service pickers', [
    'reranker' => ['reranker', AiProvider::OpenRouter],
    'document reading' => ['document_parser', AiProvider::Mistral],
    'summaries and PDF chat' => ['document_llm', AiProvider::Mistral],
    'speech to text' => ['transcriber', AiProvider::Scaleway],
    'voice' => ['voice', AiProvider::OpenAi],
]);

it('shows the service in use, marks each provider connected or not, and greys out the others', function (string $property, AiProvider $provider, array $options, array $disabled): void {
    connectAiProviders($provider);

    $field = scField(adminPage(ManageAiModelSettings::class, $this->admin)->assertFormSet([$property => $provider->value]), $property);

    expect($field->getOptions())->toBe($options)
        ->and($field->canSelectPlaceholder())->toBeFalse()
        ->and(array_keys(array_filter($options, fn (string $label, string $value): bool => $field->isOptionDisabled($value, $label), ARRAY_FILTER_USE_BOTH)))->toBe($disabled);
})->with([
    'reranker' => ['reranker', AiProvider::OpenRouter, ['openrouter' => 'OpenRouter (connected)', 'cohere' => 'Cohere Rerank (not connected)', 'custom' => 'Self-hosted', 'none' => 'Off (a basic similarity check)'], ['cohere']],
    'document reading' => ['document_parser', AiProvider::Mistral, ['mistral' => 'Mistral OCR (connected)', 'azure' => 'Azure Document Intelligence (not connected)', 'none' => 'Off'], ['azure']],
    'summaries and PDF chat' => ['document_llm', AiProvider::Mistral, ['mistral' => 'Mistral (connected)', 'none' => 'Off'], []],
    'speech to text' => ['transcriber', AiProvider::Scaleway, ['scaleway' => 'Scaleway AI Whisper (connected)', 'openai' => 'OpenAI Whisper (not connected)', 'none' => 'Off'], ['openai']],
    'voice' => ['voice', AiProvider::OpenAi, ['openai' => 'OpenAI Realtime (connected)', 'none' => 'Off'], []],
]);

it('shows Off, and greys out the provider, while nothing is connected', function (string $property, AiProvider $provider): void {
    $field = scField(adminPage(ManageAiModelSettings::class, $this->admin)->assertFormSet([$property => 'none']), $property);

    expect($field->isOptionDisabled($provider->value, $provider->label()))->toBeTrue();
})->with('service pickers');

it('refuses a provider that is not connected, without a call', function (string $property, AiProvider $provider): void {
    Http::fake();

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->fillForm([$property => $provider->value])
        ->call('save')
        ->assertHasFormErrors([$property => "{$provider->label()} is not connected. Add its key in AI providers first."]);

    Http::assertNotSent(scServiceCall(...));
})->with('service pickers');

it('calls the service once, though a save reads the form twice', function (string $property, AiProvider $provider, string $url, array $answer): void {
    saveSettings(AiSettings::class, [$property => 'none']);
    connectAiProviders($provider);
    Http::fake([$url => Http::response($answer)]);

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->fillForm([$property => $provider->value])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(savedSetting(AiSettings::class, $property))->toBe($provider->value)
        ->and(Http::recorded(scServiceCall(...)))->toHaveCount(1);
})->with([
    'reranker' => ['reranker', AiProvider::OpenRouter, 'openrouter.ai/api/v1/rerank', ['results' => [['index' => 0, 'relevance_score' => 0.9], ['index' => 1, 'relevance_score' => 0.1]]]],
    'document reading' => ['document_parser', AiProvider::Mistral, 'api.mistral.ai/v1/ocr', ['pages' => [['index' => 0, 'markdown' => 'Chatvia OCR check']]]],
    'summaries and PDF chat' => ['document_llm', AiProvider::Mistral, 'api.mistral.ai/v1/models', ['data' => [['id' => 'mistral-medium-latest', 'capabilities' => ['completion_chat' => true]]]]],
    'speech to text' => ['transcriber', AiProvider::Scaleway, 'api.scaleway.ai/v1/models', ['object' => 'list', 'data' => [['id' => 'whisper-large-v3', 'object' => 'model']]]],
    'voice' => ['voice', AiProvider::OpenAi, 'api.openai.com/v1/models', ['data' => [['id' => 'gpt-realtime-2.1', 'object' => 'model']]]],
]);

it('does not call the service again when the pick is unchanged', function (string $property, AiProvider $provider): void {
    saveSettings(AiSettings::class, [$property => $provider->value]);
    connectAiProviders($provider);
    Http::fake();

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->assertFormSet([$property => $provider->value])
        ->call('save')
        ->assertHasNoFormErrors();

    Http::assertNotSent(scServiceCall(...));
})->with('service pickers');

it('turns a service off without a call', function (string $property, AiProvider $provider): void {
    saveSettings(AiSettings::class, [$property => $provider->value]);
    connectAiProviders($provider);
    Http::fake();

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->assertFormSet([$property => $provider->value])
        ->fillForm([$property => 'none'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(savedSetting(AiSettings::class, $property))->toBe('none');
    Http::assertNotSent(scServiceCall(...));
})->with('service pickers');
