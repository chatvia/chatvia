<?php

use App\Admin\Clusters\Settings\Pages\ManageAiModelSettings;
use App\Admin\Settings\AiSettings;
use Domain\Llm\Enums\AiProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * The Voice conversations card of Settings → AI models: turning voice on
 * checks the OpenAI key with a real call before it is saved.
 */
beforeEach(function (): void {
    $this->admin = adminUser();
});

it('checks the OpenAI key before it turns voice on', function (): void {
    connectAiProviders(AiProvider::OpenAi);
    config(['chatvia.ai.voice' => 'none']);
    Http::fake(['api.openai.com/v1/models' => Http::response(['data' => [['id' => 'gpt-realtime-2.1', 'object' => 'model']]])]);

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->fillForm(['voice' => 'openai'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(savedSetting(AiSettings::class, 'voice'))->toBe('openai');
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.openai.com/v1/models'
        && $request->hasHeader('Authorization', 'Bearer test-key'));
});
