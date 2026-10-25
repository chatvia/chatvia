<?php

use Domain\Llm\Exceptions\SpeechNotConfiguredException;
use Domain\Llm\Operations\Media\NullSpeaker;

/**
 * No text-to-speech service: off, with no voices, and reading fails with
 * the "not set up" error.
 */
it('is off and has no voices', function (): void {
    $speaker = new NullSpeaker;

    expect($speaker->isEnabled())->toBeFalse()
        ->and($speaker->voices())->toBe([])
        ->and($speaker->defaultVoice())->toBe('');
});

it('refuses to read', function (): void {
    (new NullSpeaker)->speak('Hi.', 'any');
})->throws(SpeechNotConfiguredException::class);
