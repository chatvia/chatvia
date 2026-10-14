<?php

use App\Admin\Clusters\Settings\Pages\ManageAiModelSettings;
use App\Admin\Clusters\Settings\Pages\ManagePlatformSettings;
use App\Admin\Settings\AiProviderUsage;
use App\Identity\Workspace\Models\User;
use Domain\Conversation\Services\Voice\VoiceCallSlots;
use Domain\Llm\Contracts\RealtimeCalls;
use Domain\Llm\Enums\AiCapability;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Enums\RealtimeVoice;
use Domain\Llm\Operations\Realtime\NullRealtimeCalls;
use Domain\Llm\Operations\Realtime\OpenAiRealtimeCalls;
use Livewire\Livewire;

/**
 * The Voice conversations card of Settings → AI models (on or off, the
 * default voice, the longest call, the model) and the live voice calls on
 * Settings → Platform → Queue workers. "Automatic" turns voice on once OpenAI
 * is connected; OpenAI cannot be picked before it is.
 */
beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create()->fresh();
});

it('turns voice on with OpenAI once it is connected, and off without it', function (): void {
    config(['chatvia.ai.voice' => 'auto', 'services.openai.api_key' => null]);
    expect(app(RealtimeCalls::class))->toBeInstanceOf(NullRealtimeCalls::class);

    connectAiProviders(AiProvider::OpenAi);

    expect(app(RealtimeCalls::class))->toBeInstanceOf(OpenAiRealtimeCalls::class)
        ->and(app(RealtimeCalls::class)->isEnabled())->toBeTrue()
        ->and(app(AiProviderUsage::class)->providerFor(AiCapability::Voice))->toBe(AiProvider::OpenAi);
});

it('saves the voice, the longest call and the model', function (): void {
    connectAiProviders(AiProvider::OpenAi);

    Livewire::actingAs($this->admin, 'admin')
        ->test(ManageAiModelSettings::class)
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

it('refuses OpenAI before it is connected', function (): void {
    config(['services.openai.api_key' => null]);

    Livewire::actingAs($this->admin, 'admin')
        ->test(ManageAiModelSettings::class)
        ->fillForm(['voice' => 'openai'])
        ->call('save')
        ->assertHasFormErrors(['voice' => 'Connect OpenAI in AI providers first.']);
});

it('refuses a call longer than the queue allows', function (): void {
    Livewire::actingAs($this->admin, 'admin')
        ->test(ManageAiModelSettings::class)
        ->fillForm(['voice_max_call_minutes' => 30])
        ->call('save')
        ->assertHasFormErrors(['voice_max_call_minutes']);
});

it('sets how many voice calls run at once', function (): void {
    Livewire::actingAs($this->admin, 'admin')
        ->test(ManagePlatformSettings::class)
        ->fillForm(['voice_workers' => 6])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(config('horizon.environments.production.voice.maxProcesses'))->toBe(6);

    app()->detectEnvironment(fn (): string => 'production');
    expect(app(VoiceCallSlots::class)->capacity())->toBe(6);
});
