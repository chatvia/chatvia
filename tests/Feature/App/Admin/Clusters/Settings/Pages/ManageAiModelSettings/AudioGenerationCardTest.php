<?php

use App\Admin\Clusters\Settings\Pages\ManageAiModelSettings;
use App\Admin\Settings\AiSettings;
use Domain\Llm\Contracts\Speaker;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Operations\Media\ElevenLabsSpeaker;
use Domain\Llm\Operations\Media\NullSpeaker;
use Domain\Llm\Operations\Media\OpenAiSpeaker;
use Domain\Llm\Services\AudioGeneration;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * The Audio generation card of Settings → AI models: a speech service of its
 * own for the audio files agents make, apart from Read aloud and off until an
 * admin picks one, the most files one chat gets in an hour, and the longest
 * text one file reads. The voices are the Read aloud card's, shown for the
 * services either card uses.
 */
beforeEach(function (): void {
    $this->admin = adminUser();

    foreach (AiProvider::cases() as $provider) {
        config([$provider->apiKeyConfig() => null]);
    }
});

it('stays off while a key is connected and nothing is picked', function (): void {
    connectAiProviders(AiProvider::OpenAi, AiProvider::ElevenLabs);

    $page = adminPage(ManageAiModelSettings::class, $this->admin)->assertFormSet(['audio_generator' => 'none']);
    /** @var Select $field */
    $field = $page->instance()->form->getFlatFields(withHidden: true)['audio_generator'];

    expect(collect($field->getOptions())->map(fn (string $label): string => Str::of($label)->stripTags()->trim()->toString())->all())->toBe([
        'elevenlabs' => 'ElevenLabs (connected)',
        'openai' => 'OpenAI Realtime (connected)',
        'none' => 'Off',
    ])->and(app(AudioGeneration::class)->speaker)->toBeInstanceOf(NullSpeaker::class);
});

it('saves its own service and limits, apart from Read aloud', function (): void {
    saveSettings(AiSettings::class, ['speaker' => 'openai']);
    connectAiProviders(AiProvider::OpenAi, AiProvider::ElevenLabs);
    Http::fake(['api.elevenlabs.io/*' => Http::response(['voices' => [], 'subscription' => []]), '*' => Http::response(['data' => []])]);

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->fillForm([
            'audio_generator' => 'elevenlabs',
            'audio_per_chat_per_hour' => 3,
            'audio_max_characters' => 30000,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(config('chatvia.ai.audio_generator'))->toBe('elevenlabs')
        ->and(config('chatvia.ai.audio_per_chat_per_hour'))->toBe(3)
        ->and(config('chatvia.ai.audio_max_characters'))->toBe(30000)
        ->and(app(AudioGeneration::class)->speaker)->toBeInstanceOf(ElevenLabsSpeaker::class)
        ->and(app(Speaker::class))->toBeInstanceOf(OpenAiSpeaker::class);
});

it('shows the voice of the service audio files use, though Read aloud uses another', function (): void {
    saveSettings(AiSettings::class, ['speaker' => 'openai', 'audio_generator' => 'elevenlabs']);
    connectAiProviders(AiProvider::OpenAi, AiProvider::ElevenLabs);
    Http::fake(['*' => Http::response(['voices' => []])]);

    $fields = adminPage(ManageAiModelSettings::class, $this->admin)->instance()->form->getFlatFields();

    expect($fields)->toHaveKey('openai_speech_voice')
        ->and($fields)->toHaveKey('elevenlabs_speech_voice');
});

it('refuses a longest text out of range', function (int $characters): void {
    adminPage(ManageAiModelSettings::class, $this->admin)
        ->fillForm(['audio_max_characters' => $characters])
        ->call('save')
        ->assertHasFormErrors(['audio_max_characters']);
})->with([500, 60000]);
