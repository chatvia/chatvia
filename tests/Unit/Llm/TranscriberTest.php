<?php

use App\Http\Controllers\Chat\Transcription\ChatTranscriptionController;
use App\Http\Requests\Chat\TranscribeAudioRequest;
use Domain\Conversation\Actions\Messages\TranscribeAudioAction;
use Domain\Llm\Exceptions\TranscriptionNotConfiguredException;
use Domain\Llm\Operations\Media\NullTranscriber;
use Illuminate\Http\UploadedFile;

/**
 * Without a speech-to-text service the transcriber fails with a clear "not
 * configured" error: chat voice input answers 503 with that message, and an
 * audio source fails with it as its reason.
 */
it('throws a clear "not configured" error', function (): void {
    expect(fn (): string => (new NullTranscriber)->transcribe('/tmp/clip.mp3'))
        ->toThrow(TranscriptionNotConfiguredException::class, 'Audio transcription is not configured on this server.');
});

it('answers chat voice input with 503 and a message the composer shows when transcription is not configured', function (): void {
    $action = Mockery::mock(TranscribeAudioAction::class);
    $action->shouldReceive('execute')->once()->andThrow(new TranscriptionNotConfiguredException);

    $request = TranscribeAudioRequest::create('/chat/transcribe', 'POST', files: [
        'audio' => UploadedFile::fake()->create('clip.webm', 10, 'audio/webm'),
    ]);

    $response = (new ChatTranscriptionController)($request, $action);

    expect($response->getStatusCode())->toBe(503)
        ->and($response->getData(true))->toBe(['message' => 'Voice input is not available right now.']);
});
