<?php

use App\Admin\Clusters\Settings\Pages\ManageAiModelSettings;
use App\Admin\Settings\AiSettings;
use Domain\Llm\Enums\AiProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * The Speech to text card of Settings → AI models: Scaleway AI Whisper,
 * OpenAI Whisper or Off. A new pick checks the provider's key with a real
 * call as it is saved, and is refused when the call fails. What the pickers
 * share is in ServiceChoicesTest.
 */
beforeEach(function (): void {
    $this->admin = adminUser();

    config([
        'chatvia.ai.transcriber' => 'none',
        AiProvider::Scaleway->apiKeyConfig() => null,
        AiProvider::OpenAi->apiKeyConfig() => null,
    ]);
});

it('checks the Scaleway AI key before it turns speech to text on', function (): void {
    connectAiProviders(AiProvider::Scaleway);
    Http::fake(['api.scaleway.ai/v1/models' => Http::response(['object' => 'list', 'data' => [['id' => 'whisper-large-v3', 'object' => 'model']]])]);

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->fillForm(['transcriber' => 'scaleway'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(savedSetting(AiSettings::class, 'transcriber'))->toBe('scaleway');
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.scaleway.ai/v1/models'
        && $request->hasHeader('Authorization', 'Bearer test-key'));
});

it('refuses OpenAI Whisper when OpenAI refuses the key', function (): void {
    connectAiProviders(AiProvider::OpenAi);
    Http::fake(['api.openai.com/v1/models' => Http::response(['error' => ['message' => 'Incorrect API key provided']], 401)]);

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->fillForm(['transcriber' => 'openai'])
        ->call('save')
        ->assertHasFormErrors(['transcriber' => 'OpenAI refused the API key. Check that you copied all of it.']);

    expect(savedSetting(AiSettings::class, 'transcriber'))->toBeNull();
});
