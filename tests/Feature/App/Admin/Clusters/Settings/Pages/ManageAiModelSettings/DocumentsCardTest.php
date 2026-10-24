<?php

use App\Admin\Clusters\Settings\Pages\ManageAiModelSettings;
use App\Admin\Settings\AiProviderSettings;
use App\Admin\Settings\AiSettings;
use Domain\Llm\Services\DocumentLlm;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * The Documents card of Settings → AI models: document reading (Mistral OCR,
 * Azure Document Intelligence or Off), source summaries (Mistral or Off),
 * and chat file uploads with their model. A new service pick is checked
 * with a real call as it is saved and refused, with the reason, when the
 * call fails. What the pickers share is in ServiceChoicesTest.
 */
beforeEach(function (): void {
    $this->admin = adminUser();

    saveSettings(AiProviderSettings::class, ['mistral_api_key' => null, 'azure_api_key' => null, 'azure_endpoint' => null]);
    saveSettings(AiSettings::class, ['document_parser' => null, 'document_llm' => null]);
});

it('reads a test image with Mistral OCR before it saves Mistral OCR', function (): void {
    saveSettings(AiProviderSettings::class, ['mistral_api_key' => 'mistral-key']);
    saveSettings(AiSettings::class, ['document_parser' => 'none']);
    Http::fake(['api.mistral.ai/v1/ocr' => Http::response(['pages' => [['index' => 0, 'markdown' => 'Chatvia OCR check']]])]);

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->fillForm(['document_parser' => 'mistral'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(savedSetting(AiSettings::class, 'document_parser'))->toBe('mistral');
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.mistral.ai/v1/ocr'
        && $request->hasHeader('Authorization', 'Bearer mistral-key')
        && $request['model'] === config('services.mistral.ocr_model')
        && $request['document']['type'] === 'image_url'
        && Str::startsWith($request['document']['image_url'], 'data:image/png;base64,'));
});

it('refuses Mistral OCR when Mistral refuses the key', function (): void {
    saveSettings(AiProviderSettings::class, ['mistral_api_key' => 'mistral-key']);
    saveSettings(AiSettings::class, ['document_parser' => 'none']);
    Http::fake(['api.mistral.ai/v1/ocr' => Http::response(['message' => 'Unauthorized'], 401)]);

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->fillForm(['document_parser' => 'mistral'])
        ->call('save')
        ->assertHasFormErrors(['document_parser' => 'Mistral refused the key. Check it in AI providers.']);

    expect(savedSetting(AiSettings::class, 'document_parser'))->toBe('none');
});

it('says why Mistral OCR failed', function (): void {
    saveSettings(AiProviderSettings::class, ['mistral_api_key' => 'mistral-key']);
    saveSettings(AiSettings::class, ['document_parser' => 'none']);
    Http::fake(['api.mistral.ai/v1/ocr' => Http::response(['message' => 'Invalid model: mistral-ocr-nope'], 400)]);

    $page = adminPage(ManageAiModelSettings::class, $this->admin)
        ->fillForm(['document_parser' => 'mistral'])
        ->call('save')
        ->assertHasFormErrors(['document_parser']);

    expect($page->errors()->first('data.document_parser'))->toBe('Mistral OCR answered with an error (400): Invalid model: mistral-ocr-nope');
});

it('checks the Azure resource before it saves Azure', function (): void {
    saveSettings(AiProviderSettings::class, ['azure_api_key' => 'az-key', 'azure_endpoint' => 'https://docs.cognitiveservices.azure.com']);
    saveSettings(AiSettings::class, ['document_parser' => 'none']);
    Http::fake(['docs.cognitiveservices.azure.com/*' => Http::response(['customDocumentModels' => ['count' => 0, 'limit' => 5000]])]);

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->fillForm(['document_parser' => 'azure'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(savedSetting(AiSettings::class, 'document_parser'))->toBe('azure');
    Http::assertSent(fn (Request $request): bool => Str::contains($request->url(), '/documentintelligence/info'));
});

it('refuses Azure when its endpoint does not answer', function (): void {
    saveSettings(AiProviderSettings::class, ['azure_api_key' => 'az-key', 'azure_endpoint' => 'https://docs.cognitiveservices.azure.com']);
    saveSettings(AiSettings::class, ['document_parser' => 'none']);
    Http::fake(['docs.cognitiveservices.azure.com/*' => Http::response(['error' => ['code' => '404']], 404)]);

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->fillForm(['document_parser' => 'azure'])
        ->call('save')
        ->assertHasFormErrors(['document_parser' => 'Azure answered with an error (404). Check the endpoint.']);

    expect(savedSetting(AiSettings::class, 'document_parser'))->toBe('none');
});

it('checks the Mistral key before it turns on summaries and PDF chat', function (): void {
    saveSettings(AiProviderSettings::class, ['mistral_api_key' => 'mistral-key']);
    saveSettings(AiSettings::class, ['document_llm' => 'none']);
    Http::fake(['api.mistral.ai/v1/models' => Http::response(['data' => [['id' => 'mistral-medium-latest', 'capabilities' => ['completion_chat' => true]]]])]);

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->fillForm(['document_llm' => 'mistral'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(savedSetting(AiSettings::class, 'document_llm'))->toBe('mistral')
        ->and(app(DocumentLlm::class)->isAvailable())->toBeTrue();
});

it('lists only the models that read PDF files and images for chat file uploads, and saves the switch and the pick', function (): void {
    $reads = fn (array $inputs): array => ['reasoning' => false, 'modalities' => ['input' => ['text', ...$inputs]]];
    testLlm('images-only', attributes: ['capabilities' => $reads(['image'])]);
    $both = testLlm('pdf-and-images', attributes: ['capabilities' => $reads(['image', 'pdf'])]);

    $page = adminPage(ManageAiModelSettings::class, $this->admin)->fillForm(['chat_files_enabled' => true]);

    expect(array_merge(...array_map(array_keys(...), array_values($page->instance()->form->getFlatFields(withHidden: true)['chat_files_model']->getOptions()))))->toBe([$both->id]);

    $page->fillForm(['chat_files_model' => (string) $both->id])->call('save')->assertHasNoFormErrors();

    expect(savedSetting(AiSettings::class, 'chat_files_model'))->toBe((string) $both->id);

    adminPage(ManageAiModelSettings::class, $this->admin)->fillForm(['chat_files_enabled' => false])->call('save')->assertHasNoFormErrors();

    expect(savedSetting(AiSettings::class, 'chat_files_enabled'))->toBeFalse();
});
