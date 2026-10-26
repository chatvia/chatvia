<?php

use Domain\Llm\Data\Speech\SpeechVoiceData;
use Domain\Llm\Enums\SpeechFormat;
use Domain\Llm\Exceptions\SpeechFailedException;
use Domain\Llm\Operations\Media\ElevenLabsSpeaker;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * ElevenLabs text to speech (Settings → AI models → Read aloud): the text
 * goes to the voice's endpoint with the configured model, a long text in
 * parts that make one MP3, and the agent's voices come from the account.
 */
beforeEach(function (): void {
    config([
        'services.elevenlabs.api_key' => 'xi-key',
        'services.elevenlabs.endpoint' => 'https://api.elevenlabs.test/v1',
        'services.elevenlabs.speech_model' => 'eleven_flash_v2_5',
        'services.elevenlabs.speech_voice' => null,
    ]);
});

it('reads the text with the voice and the configured model, as MP3', function (): void {
    Http::fake(['*' => Http::response('mp3-bytes', 200, ['Content-Type' => 'audio/mpeg'])]);

    $speaker = app(ElevenLabsSpeaker::class);

    expect($speaker->speak('Hello there.', 'voice123'))->toBe('mp3-bytes')
        ->and($speaker->format())->toBe(SpeechFormat::Mp3)
        ->and($speaker->name())->toBe('elevenlabs eleven_flash_v2_5');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.elevenlabs.test/v1/text-to-speech/voice123?output_format=mp3_44100_128'
        && $request->hasHeader('xi-api-key', 'xi-key')
        && $request['text'] === 'Hello there.'
        && $request['model_id'] === 'eleven_flash_v2_5');
});

it('reads a long text in parts that make one file', function (): void {
    Http::fake(['*' => Http::sequence()->push('one')->push('two')]);

    $text = Str::repeat('A sentence of words here. ', 250).'Last one.';

    expect(app(ElevenLabsSpeaker::class)->speak($text, 'v1'))->toBe('onetwo');
    Http::assertSentCount(2);
});

it('fails clearly on an error from ElevenLabs', function (): void {
    Http::fake(['*' => Http::response(['detail' => 'quota'], 401)]);

    app(ElevenLabsSpeaker::class)->speak('Hi.', 'v1');
})->throws(SpeechFailedException::class);

it('offers the account\'s professional voices, and the admin\'s pick too', function (): void {
    Http::fake(['api.elevenlabs.test/v1/voices' => Http::response(['voices' => [
        ['voice_id' => 'v1', 'name' => 'Ana', 'labels' => ['accent' => 'spanish', 'use_case' => 'narration'], 'verified_languages' => [['language' => 'es'], ['language' => 'en']]],
        ['voice_id' => 'v2', 'name' => 'Ben', 'labels' => ['accent' => 'american', 'use_case' => 'news'], 'verified_languages' => [['language' => 'en'], ['language' => 'de'], ['language' => 'fr']]],
        ['voice_id' => 'v3', 'name' => 'Zed', 'labels' => ['use_case' => 'characters']],
    ]])]);

    $speaker = app(ElevenLabsSpeaker::class);

    expect(array_map(fn (SpeechVoiceData $voice): array => $voice->toArray(), $speaker->voices()))->toBe([
        ['id' => 'v2', 'label' => 'Ben (American, News)'],
        ['id' => 'v1', 'label' => 'Ana (Spanish, Narration)'],
    ])->and($speaker->defaultVoice())->toBe('v2');

    config(['services.elevenlabs.speech_voice' => 'v3']);

    expect($speaker->defaultVoice())->toBe('v3')
        ->and(array_column(array_map(fn (SpeechVoiceData $voice): array => $voice->toArray(), $speaker->voices()), 'id'))->toBe(['v3', 'v2', 'v1']);
});
