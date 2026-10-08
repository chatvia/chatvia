<?php

use Domain\Conversation\Actions\Messages\TranscribeAudioAction;
use Domain\Knowledge\Ingestion\Services\Media\AudioCompressor;
use Domain\Llm\Contracts\Transcriber;
use Domain\Llm\Exceptions\TranscriptionNotConfiguredException;
use Domain\Llm\Operations\Media\NullTranscriber;
use Illuminate\Http\UploadedFile;

/**
 * Chat voice input: the upload is compressed, handed to the configured
 * Transcriber, and the local copy is removed whatever the outcome.
 */
function taaCompressor(): AudioCompressor
{
    $compressor = Mockery::mock(AudioCompressor::class);
    $compressor->shouldReceive('process')->once()->andReturnUsing(fn (string $path): string => $path);

    return $compressor;
}

it('transcribes the compressed audio with the configured transcriber and removes the local copy', function (): void {
    $transcriber = new class implements Transcriber
    {
        public ?string $path = null;

        public bool $fileExisted = false;

        public function transcribe(string $filePath): string
        {
            $this->path = $filePath;
            $this->fileExisted = is_file($filePath);

            return 'hello there';
        }
    };

    $text = (new TranscribeAudioAction(taaCompressor(), $transcriber))
        ->execute(UploadedFile::fake()->create('clip.webm', 5, 'audio/webm'));

    expect($text)->toBe('hello there')
        ->and($transcriber->fileExisted)->toBeTrue()
        ->and(file_exists((string) $transcriber->path))->toBeFalse();
});

it('fails with the "not configured" error when no transcriber is set up', function (): void {
    $action = new TranscribeAudioAction(taaCompressor(), new NullTranscriber);

    expect(fn (): string => $action->execute(UploadedFile::fake()->create('clip.webm', 5, 'audio/webm')))
        ->toThrow(TranscriptionNotConfiguredException::class);
});
