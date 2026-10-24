<?php

use App\Admin\Clusters\Settings\Pages\ManageAiModelSettings;
use App\Admin\Settings\AiSettings;
use Domain\Llm\Contracts\RealtimeCalls;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Enums\RealtimeVoice;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * The Voice conversations card of Settings → AI models: on or off, the
 * default voice, the longest call and the model. Turning voice on checks
 * the OpenAI key with a real call, and is refused when it fails. What the
 * pickers share is in ServiceChoicesTest; the live calls at once are a
 * number of Settings → Platform → Queue workers.
 */
beforeEach(function (): void {
    $this->admin = adminUser();
});

it('saves the voice, the longest call and the model', function (): void {
    connectAiProviders(AiProvider::OpenAi);

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->fillForm([
            'voice' => 'openai',
            'openai_realtime_voice' => 'cedar',
            'voice_max_call_minutes' => 5,
            'openai_realtime_model' => 'gpt-realtime-2.1-mini',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(config('chatvia.ai.voice'))->toBe('openai')
        ->and(RealtimeVoice::configured())->toBe(RealtimeVoice::Cedar)
        ->and(config('chatvia.voice.max_call_minutes'))->toBe(5)
        ->and(app(RealtimeCalls::class)->model())->toBe('gpt-realtime-2.1-mini');
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

it('refuses voice when OpenAI refuses the key', function (): void {
    connectAiProviders(AiProvider::OpenAi);
    config(['chatvia.ai.voice' => 'none']);
    Http::fake(['api.openai.com/v1/models' => Http::response(['error' => ['message' => 'Incorrect API key provided']], 401)]);

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->fillForm(['voice' => 'openai'])
        ->call('save')
        ->assertHasFormErrors(['voice' => 'OpenAI refused the API key. Check that you copied all of it.']);

    expect(savedSetting(AiSettings::class, 'voice'))->toBeNull();
});

it('refuses a call longer than the queue allows', function (): void {
    adminPage(ManageAiModelSettings::class, $this->admin)
        ->fillForm(['voice_max_call_minutes' => 30])
        ->call('save')
        ->assertHasFormErrors(['voice_max_call_minutes']);
});
