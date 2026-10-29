<?php

use App\Admin\Clusters\Settings\Pages\ManageAiModelSettings;
use App\Admin\Settings\AiSettings;
use Domain\Llm\Contracts\Speaker;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Operations\Media\ElevenLabsSpeaker;
use Domain\Llm\Operations\Media\OpenAiSpeaker;
use Domain\Llm\Services\AudioGeneration;
use Illuminate\Support\Facades\Http;

/**
 * The Audio generation card of Settings → AI models: a speech service of its
 * own for the audio files agents make, apart from Read aloud, with the most
 * files one chat gets in an hour and the longest text one file reads.
 */
beforeEach(function (): void {
    $this->admin = adminUser();

    foreach (AiProvider::cases() as $provider) {
        config([$provider->apiKeyConfig() => null]);
    }
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
