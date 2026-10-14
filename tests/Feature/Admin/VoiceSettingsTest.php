<?php

use App\Admin\Clusters\Settings\Pages\ManageAiModelSettings;
use App\Admin\Clusters\Settings\Pages\ManagePlatformSettings;
use App\Admin\Settings\AiProviderUsage;
use App\Admin\Settings\AiSettings;
use App\Identity\Workspace\Models\User;
use Domain\Conversation\Services\Voice\VoiceCallSlots;
use Domain\Llm\Contracts\RealtimeCalls;
use Domain\Llm\Enums\AiCapability;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Enums\RealtimeVoice;
use Domain\Llm\Operations\Realtime\NullRealtimeCalls;
use Domain\Llm\Operations\Realtime\OpenAiRealtimeCalls;
use Filament\Forms\Components\Select;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * The Voice conversations card of Settings → AI models (on or off, the
 * default voice, the longest call, the model) and the live voice calls on
 * Settings → Platform → Queue workers. While nothing is saved, voice is on
 * once OpenAI is connected. The list has no "Automatic": it shows what is in
 * use, and greys out OpenAI Realtime until OpenAI is connected. Turning voice
 * on checks the OpenAI key with a real call, and is refused when it fails.
 */
beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create()->fresh();
});

function voicePage(User $admin): Testable
{
    return Livewire::actingAs($admin, 'admin')->test(ManageAiModelSettings::class);
}

function voiceField(Testable $page): Select
{
    return $page->instance()->form->getFlatFields(withHidden: true)['voice'];
}

function savedVoice(): ?string
{
    app()->forgetScopedInstances();

    return app(AiSettings::class)->voice;
}

/** Whether a request went to OpenAI (the page lists OpenRouter's models too). */
function openAiCall(Request $request): bool
{
    return str_contains($request->url(), 'api.openai.com');
}

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

it('shows the voice service in use, marked connected, with no Automatic', function (): void {
    connectAiProviders(AiProvider::OpenAi);

    $field = voiceField(voicePage($this->admin)->assertFormSet(['voice' => 'openai']));

    expect($field->getOptions())->toBe(['openai' => 'OpenAI Realtime (connected)', 'none' => 'Off'])
        ->and($field->canSelectPlaceholder())->toBeFalse()
        ->and($field->isOptionDisabled('openai', 'OpenAI Realtime'))->toBeFalse();
});

it('shows Off, and greys out OpenAI Realtime, until OpenAI is connected', function (): void {
    config(['services.openai.api_key' => null]);

    $field = voiceField(voicePage($this->admin)->assertFormSet(['voice' => 'none']));

    expect($field->getOptions())->toBe(['openai' => 'OpenAI Realtime (not connected)', 'none' => 'Off'])
        ->and($field->isOptionDisabled('openai', 'OpenAI Realtime'))->toBeTrue();
});

it('checks the OpenAI key before it turns voice on', function (): void {
    connectAiProviders(AiProvider::OpenAi);
    config(['chatvia.ai.voice' => 'none']);
    Http::fake(['api.openai.com/v1/models' => Http::response(['data' => [['id' => 'gpt-realtime-2.1', 'object' => 'model']]])]);

    voicePage($this->admin)
        ->fillForm(['voice' => 'openai'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(savedVoice())->toBe('openai')
        ->and(Http::recorded(openAiCall(...)))->toHaveCount(1);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.openai.com/v1/models'
        && $request->hasHeader('Authorization', 'Bearer test-key'));
});

it('refuses voice when OpenAI refuses the key', function (): void {
    connectAiProviders(AiProvider::OpenAi);
    config(['chatvia.ai.voice' => 'none']);
    Http::fake(['api.openai.com/v1/models' => Http::response(['error' => ['message' => 'Incorrect API key provided']], 401)]);

    voicePage($this->admin)
        ->fillForm(['voice' => 'openai'])
        ->call('save')
        ->assertHasFormErrors(['voice' => 'OpenAI refused the API key. Check that you copied all of it.']);

    expect(savedVoice())->toBeNull();
});

it('does not call OpenAI again when voice is unchanged', function (): void {
    connectAiProviders(AiProvider::OpenAi);
    config(['chatvia.ai.voice' => 'openai']);
    Http::fake();

    voicePage($this->admin)
        ->call('save')
        ->assertHasNoFormErrors();

    Http::assertNotSent(openAiCall(...));
});

it('refuses OpenAI before it is connected', function (): void {
    config(['services.openai.api_key' => null]);
    Http::fake();

    voicePage($this->admin)
        ->fillForm(['voice' => 'openai'])
        ->call('save')
        ->assertHasFormErrors(['voice' => 'OpenAI is not connected. Add its key in AI providers first.']);

    Http::assertNotSent(openAiCall(...));
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
