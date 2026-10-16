<?php

use Domain\Llm\Exceptions\TranscriptionNotConfiguredException;
use Domain\Llm\Operations\Media\NullTranscriber;

/**
 * Without a speech-to-text service the transcriber fails with a clear "not
 * configured" error: chat voice input answers 503 with it (VoiceCallTest and
 * WidgetTranscriptionTest), and an audio source fails with it as its reason.
 */
it('throws a clear "not configured" error', function (): void {
    expect(fn (): string => (new NullTranscriber)->transcribe('/tmp/clip.mp3'))
        ->toThrow(TranscriptionNotConfiguredException::class, 'Audio transcription is not configured on this server.');
});
