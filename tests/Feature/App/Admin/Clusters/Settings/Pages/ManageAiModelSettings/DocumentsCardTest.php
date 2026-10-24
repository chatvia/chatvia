<?php

use App\Admin\Clusters\Settings\Pages\ManageAiModelSettings;
use App\Admin\Settings\AiProviderSettings;
use App\Admin\Settings\AiSettings;
use Domain\Llm\Models\Llm;
use Domain\Llm\Services\SourceSummaryModel;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * The Documents card of Settings → AI models: document reading (Mistral OCR,
 * Azure Document Intelligence or Off), source summaries and chat file
 * uploads, each with its model. A new reader is checked with a real call as
 * it is saved and refused, with the reason, when the call fails. What the pickers share is in ServiceChoicesTest.
 */
beforeEach(function (): void {
    $this->admin = adminUser();

    saveSettings(AiProviderSettings::class, ['mistral_api_key' => null, 'azure_api_key' => null, 'azure_endpoint' => null]);
    saveSettings(AiSettings::class, ['document_parser' => null, 'source_summaries_enabled' => null, 'source_summaries_model' => null]);
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

it('lists only the models that read PDF files for source summaries, of any provider, and saves the switch and the pick', function (): void {
    $reads = fn (array $inputs): array => ['reasoning' => false, 'modalities' => ['input' => ['text', ...$inputs]]];
    testLlm('images-only', attributes: ['capabilities' => $reads(['image'])]);
    $pdf = testLlm('pdf-only', attributes: ['capabilities' => $reads(['pdf'])]);

    $page = adminPage(ManageAiModelSettings::class, $this->admin)->fillForm(['source_summaries_enabled' => true]);

    expect(array_merge(...array_map(array_keys(...), array_values($page->instance()->form->getFlatFields(withHidden: true)['source_summaries_model']->getOptions()))))->toBe([$pdf->id]);

    $page->fillForm(['source_summaries_model' => (string) $pdf->id])->call('save')->assertHasNoFormErrors();

    expect(savedSetting(AiSettings::class, 'source_summaries_model'))->toBe((string) $pdf->id)
        ->and(app(SourceSummaryModel::class)->find()?->is($pdf))->toBeTrue();

    adminPage(ManageAiModelSettings::class, $this->admin)->fillForm(['source_summaries_enabled' => false])->call('save')->assertHasNoFormErrors();

    expect(savedSetting(AiSettings::class, 'source_summaries_enabled'))->toBeFalse()
        ->and(app(SourceSummaryModel::class)->isAvailable())->toBeFalse();
});

it('warns when no model reads PDF files for source summaries', function (): void {
    testLlm('text-only', attributes: ['capabilities' => ['reasoning' => false, 'modalities' => ['input' => ['text']]]]);

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->fillForm(['source_summaries_enabled' => true])
        ->assertSee('No model reads PDF files');
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

it('names the model Automatic takes in the empty choice of both model pickers', function (): void {
    $reads = fn (array $inputs): array => ['reasoning' => false, 'modalities' => ['input' => ['text', ...$inputs]]];
    testLlm('pdf-reader', attributes: ['capabilities' => $reads(['image', 'pdf'])]);
    config(['chatvia.agent.default_model' => null]);

    $fields = adminPage(ManageAiModelSettings::class, $this->admin)
        ->fillForm(['source_summaries_enabled' => true, 'chat_files_enabled' => true])
        ->instance()->form->getFlatFields(withHidden: true);

    expect($fields['source_summaries_model']->getPlaceholder())->toBe('Automatic (pdf-reader)')
        ->and($fields['chat_files_model']->getPlaceholder())->toBe('Automatic (pdf-reader)');

    Llm::query()->delete();
    Llm::flushCatalogCache();

    expect($fields['source_summaries_model']->getPlaceholder())->toBe('Automatic (no model)');
});
