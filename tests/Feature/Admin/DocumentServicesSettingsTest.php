<?php

use App\Admin\Clusters\Settings\Pages\ManageAiModelSettings;
use App\Admin\Settings\AiProviderSettings;
use App\Admin\Settings\AiSettings;
use App\Identity\Workspace\Models\User;
use Domain\Llm\Services\DocumentLlm;
use Filament\Forms\Components\Select;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * The Documents card of Settings → AI models: document reading (Mistral OCR,
 * Azure Document Intelligence or Off) and summaries and PDF chat (Mistral or
 * Off). No "Automatic": each list shows the service in use, marks each one
 * connected or not, and greys out the ones that are not. A new pick is
 * checked with a real call as it is saved and refused when the call fails.
 */
beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create()->fresh();

    documentServices(['mistral_api_key' => null, 'azure_api_key' => null, 'azure_endpoint' => null], ['document_parser' => null, 'document_llm' => null]);
});

/**
 * Saves provider keys and AI choices, as the admin panel does.
 *
 * @param  array<string, ?string>  $keys
 * @param  array<string, ?string>  $choices
 */
function documentServices(array $keys, array $choices = []): void
{
    $providers = app(AiProviderSettings::class);
    $ai = app(AiSettings::class);

    foreach ($keys as $property => $value) {
        $providers->{$property} = $value;
    }

    foreach ($choices as $property => $value) {
        $ai->{$property} = $value;
    }

    $providers->save();
    $ai->save();
    app()->forgetScopedInstances();
}

function documentsPage(User $admin): Testable
{
    return Livewire::actingAs($admin, 'admin')->test(ManageAiModelSettings::class);
}

function documentSetting(string $property): ?string
{
    app()->forgetScopedInstances();

    return app(AiSettings::class)->{$property};
}

/** Whether a request went to Mistral or Azure (the page lists OpenRouter's models too). */
function documentServiceCall(Request $request): bool
{
    return str_contains($request->url(), 'api.mistral.ai') || str_contains($request->url(), 'cognitiveservices.azure.com');
}

/** @return array<string, Select> */
function documentFields(Testable $page): array
{
    $fields = $page->instance()->form->getFlatFields(withHidden: true);

    return ['document_parser' => $fields['document_parser'], 'document_llm' => $fields['document_llm']];
}

it('shows the service in use, marked connected, with no Automatic', function (): void {
    documentServices(['mistral_api_key' => 'mistral-key']);

    $page = documentsPage($this->admin)->assertFormSet(['document_parser' => 'mistral', 'document_llm' => 'mistral']);
    $fields = documentFields($page);

    expect($fields['document_parser']->getOptions())->toBe([
        'mistral' => 'Mistral OCR (connected)',
        'azure' => 'Azure Document Intelligence (not connected)',
        'none' => 'Off',
    ])
        ->and($fields['document_llm']->getOptions())->toBe(['mistral' => 'Mistral (connected)', 'none' => 'Off'])
        ->and($fields['document_parser']->canSelectPlaceholder())->toBeFalse()
        ->and($fields['document_parser']->isOptionDisabled('azure', 'Azure'))->toBeTrue()
        ->and($fields['document_parser']->isOptionDisabled('mistral', 'Mistral OCR'))->toBeFalse()
        ->and($fields['document_parser']->isOptionDisabled('none', 'Off'))->toBeFalse();
});

it('shows Off when nothing is connected', function (): void {
    documentsPage($this->admin)->assertFormSet(['document_parser' => 'none', 'document_llm' => 'none']);
});

it('reads a test image with Mistral OCR before it saves Mistral OCR', function (): void {
    documentServices(['mistral_api_key' => 'mistral-key'], ['document_parser' => 'none']);
    Http::fake(['api.mistral.ai/v1/ocr' => Http::response(['pages' => [['index' => 0, 'markdown' => 'Chatvia OCR check']]])]);

    documentsPage($this->admin)
        ->fillForm(['document_parser' => 'mistral'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(documentSetting('document_parser'))->toBe('mistral');

    // One call, though a save reads the form twice.
    expect(Http::recorded(documentServiceCall(...)))->toHaveCount(1);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.mistral.ai/v1/ocr'
        && $request->hasHeader('Authorization', 'Bearer mistral-key')
        && $request['model'] === config('services.mistral.ocr_model')
        && $request['document']['type'] === 'image_url'
        && str_starts_with($request['document']['image_url'], 'data:image/png;base64,'));
});

it('refuses Mistral OCR when Mistral refuses the key', function (): void {
    documentServices(['mistral_api_key' => 'mistral-key'], ['document_parser' => 'none']);
    Http::fake(['api.mistral.ai/v1/ocr' => Http::response(['message' => 'Unauthorized'], 401)]);

    documentsPage($this->admin)
        ->fillForm(['document_parser' => 'mistral'])
        ->call('save')
        ->assertHasFormErrors(['document_parser' => 'Mistral refused the key. Check it in AI providers.']);

    expect(documentSetting('document_parser'))->toBe('none');
});

it('says why Mistral OCR failed', function (): void {
    documentServices(['mistral_api_key' => 'mistral-key'], ['document_parser' => 'none']);
    Http::fake(['api.mistral.ai/v1/ocr' => Http::response(['message' => 'Invalid model: mistral-ocr-nope'], 400)]);

    $page = documentsPage($this->admin)
        ->fillForm(['document_parser' => 'mistral'])
        ->call('save')
        ->assertHasFormErrors(['document_parser']);

    expect($page->errors()->first('data.document_parser'))->toBe('Mistral OCR answered with an error (400): Invalid model: mistral-ocr-nope');
});

it('checks the Azure resource before it saves Azure', function (): void {
    documentServices(['azure_api_key' => 'az-key', 'azure_endpoint' => 'https://docs.cognitiveservices.azure.com'], ['document_parser' => 'none']);
    Http::fake(['docs.cognitiveservices.azure.com/*' => Http::response(['customDocumentModels' => ['count' => 0, 'limit' => 5000]])]);

    documentsPage($this->admin)
        ->fillForm(['document_parser' => 'azure'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(documentSetting('document_parser'))->toBe('azure');
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/documentintelligence/info'));
});

it('refuses Azure when its endpoint does not answer', function (): void {
    documentServices(['azure_api_key' => 'az-key', 'azure_endpoint' => 'https://docs.cognitiveservices.azure.com'], ['document_parser' => 'none']);
    Http::fake(['docs.cognitiveservices.azure.com/*' => Http::response(['error' => ['code' => '404']], 404)]);

    documentsPage($this->admin)
        ->fillForm(['document_parser' => 'azure'])
        ->call('save')
        ->assertHasFormErrors(['document_parser' => 'Azure answered with an error (404). Check the endpoint.']);

    expect(documentSetting('document_parser'))->toBe('none');
});

it('refuses a service that is not connected', function (): void {
    Http::fake();

    documentsPage($this->admin)
        ->fillForm(['document_parser' => 'azure', 'document_llm' => 'mistral'])
        ->call('save')
        ->assertHasFormErrors([
            'document_parser' => 'Azure Document Intelligence is not connected. Add its key in AI providers first.',
            'document_llm' => 'Mistral is not connected. Add its key in AI providers first.',
        ]);

    Http::assertNotSent(documentServiceCall(...));
});

it('does not call a service again when it is unchanged', function (): void {
    documentServices(['mistral_api_key' => 'mistral-key'], ['document_parser' => 'mistral', 'document_llm' => 'mistral']);
    Http::fake();

    documentsPage($this->admin)
        ->call('save')
        ->assertHasNoFormErrors();

    Http::assertNotSent(documentServiceCall(...));
});

it('checks the Mistral key before it turns on summaries and PDF chat', function (): void {
    documentServices(['mistral_api_key' => 'mistral-key'], ['document_llm' => 'none']);
    Http::fake(['api.mistral.ai/v1/models' => Http::response(['data' => [['id' => 'mistral-medium-latest', 'capabilities' => ['completion_chat' => true]]]])]);

    documentsPage($this->admin)
        ->fillForm(['document_llm' => 'mistral'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(documentSetting('document_llm'))->toBe('mistral')
        ->and(app(DocumentLlm::class)->isAvailable())->toBeTrue();
});

it('turns summaries and PDF chat off without a call', function (): void {
    documentServices(['mistral_api_key' => 'mistral-key'], ['document_llm' => 'mistral']);
    Http::fake();

    documentsPage($this->admin)
        ->fillForm(['document_llm' => 'none'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(documentSetting('document_llm'))->toBe('none')
        ->and(app(DocumentLlm::class)->isAvailable())->toBeFalse();
    Http::assertNotSent(documentServiceCall(...));
});
