<?php

use Domain\Llm\Data\Speech\SpeechVoiceData;
use Domain\Llm\Enums\SpeechFormat;
use Domain\Llm\Operations\Media\OpenAiSpeaker;

/**
 * OpenAI reads aloud with its realtime model (the WebSocket reading itself
 * needs OpenAI, so it is not run here): WAV audio, the configured model in
 * its name, and the realtime voices with the configured default.
 */
it('makes WAV with the configured realtime model', function (): void {
    config(['services.openai.speech_model' => 'gpt-realtime-2.1-mini']);
    $speaker = app(OpenAiSpeaker::class);

    expect($speaker->format())->toBe(SpeechFormat::Wav)
        ->and($speaker->name())->toBe('openai gpt-realtime-2.1-mini');
});

it('offers the realtime voices, with the configured one as the default', function (): void {
    config(['services.openai.speech_voice' => 'cedar']);
    $speaker = app(OpenAiSpeaker::class);

    expect($speaker->defaultVoice())->toBe('cedar')
        ->and(array_map(fn (SpeechVoiceData $voice): string => $voice->id, $speaker->voices()))->toContain('marin', 'cedar');

    config(['services.openai.speech_voice' => 'nova']);

    expect($speaker->defaultVoice())->toBe('marin');
});
