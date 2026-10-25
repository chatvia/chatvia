<?php

use Domain\Llm\Services\ElevenLabsVoices;
use Illuminate\Support\Facades\Http;

/**
 * The ElevenLabs account's voices: read once and cached, nothing without a
 * key, a failed call not cached, and the recommended few are professional
 * ones that speak the most languages (any voice when none is).
 */
beforeEach(function (): void {
    config(['services.elevenlabs.api_key' => 'xi-key', 'services.elevenlabs.endpoint' => 'https://api.elevenlabs.test/v1']);
});

it('reads the voices once and keeps them', function (): void {
    Http::fake(['*' => Http::response(['voices' => [['voice_id' => 'v1', 'name' => 'Rachel'], ['name' => 'no id']]])]);

    app(ElevenLabsVoices::class)->all();
    $voices = app(ElevenLabsVoices::class)->all();

    expect($voices)->toHaveCount(1)
        ->and($voices[0]->label)->toBe('Rachel');
    Http::assertSentCount(1);
});

it('lists nothing without a key', function (): void {
    config(['services.elevenlabs.api_key' => null]);
    Http::fake();

    expect(app(ElevenLabsVoices::class)->all())->toBe([]);
    Http::assertNothingSent();
});

it('asks again after a failed call', function (): void {
    Http::fake(['*' => Http::response([], 500)]);

    app(ElevenLabsVoices::class)->all();
    app(ElevenLabsVoices::class)->all();

    Http::assertSentCount(2);
});

it('recommends any voice when the account has no professional one', function (): void {
    Http::fake(['*' => Http::response(['voices' => [['voice_id' => 'v1', 'name' => 'Zed', 'labels' => ['use_case' => 'characters']]]])]);

    expect(app(ElevenLabsVoices::class)->recommended())->toHaveCount(1);
});

it('recommends at most a few voices', function (): void {
    $voices = collect(range(1, 12))->map(fn (int $i): array => ['voice_id' => "v{$i}", 'name' => "Voice {$i}", 'labels' => ['use_case' => 'narration']])->all();
    Http::fake(['*' => Http::response(['voices' => $voices])]);

    expect(app(ElevenLabsVoices::class)->recommended())->toHaveCount(ElevenLabsVoices::RECOMMENDED);
});
