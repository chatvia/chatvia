<?php

use Domain\Conversation\Actions\Messages\TranscribeAudioAction;
use Domain\Conversation\Enums\VoiceNoteFormat;
use Domain\Knowledge\Ingestion\Services\Media\AudioCompressor;
use Domain\Knowledge\Ingestion\Services\Media\TranscriptionAudio;
use Domain\Llm\Contracts\Transcriber;
use Domain\Llm\Exceptions\TranscriptionNotConfiguredException;
use Domain\Llm\Operations\Media\NullTranscriber;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Chat voice input: the upload goes to the configured Transcriber as it is
 * when the service takes it, compressed otherwise, and every local copy is
 * removed whatever the outcome.
 */
function taaTranscriber(bool $takesAsIs): Transcriber
{
    return new class($takesAsIs) implements Transcriber
    {
        public ?string $path = null;

        public bool $fileExisted = false;

        public function __construct(private readonly bool $asIs) {}

        public function transcribe(string $filePath): string
        {
            $this->path = $filePath;
            $this->fileExisted = File::isFile($filePath);

            return 'hello there';
        }

        public function takesAsIs(string $extension, int $bytes): bool
        {
            return $this->asIs;
        }

        public function isEnabled(): bool
        {
            return true;
        }

        public function name(): string
        {
            return 'fake-whisper';
        }
    };
}

it('sends a recording the service takes as it is, without compressing it, and removes the local copy', function (): void {
    $transcriber = taaTranscriber(takesAsIs: true);
    $compressor = Mockery::mock(AudioCompressor::class);
    $compressor->shouldNotReceive('process');
    $recording = UploadedFile::fake()->create('clip.webm', 5, 'audio/webm');

    $text = new TranscribeAudioAction(new TranscriptionAudio($transcriber, $compressor), $transcriber)
        ->execute($recording->getRealPath(), VoiceNoteFormat::Webm);

    expect($text)->toBe('hello there')
        ->and($transcriber->fileExisted)->toBeTrue()
        ->and($transcriber->path)->toEndWith('.webm')
        ->and(File::exists((string) $transcriber->path))->toBeFalse()
        ->and(File::exists($recording->getRealPath()))->toBeTrue();
});

it('compresses a recording the service does not take with the demuxer of its detected format, and removes the copy and the compressed file', function (): void {
    $transcriber = taaTranscriber(takesAsIs: false);
    $compressed = create_temp_file('compressed.mp3', 'mp3-bytes');
    $compressor = Mockery::mock(AudioCompressor::class);
    $compressor->shouldReceive('process')->once()
        ->withArgs(fn (string $path, int $bitrate, ?string $inputFormat): bool => Str::endsWith($path, '.mp4') && $inputFormat === 'mp4')
        ->andReturn($compressed);
    $recording = UploadedFile::fake()->create('clip.m4a', 5, 'audio/mp4');

    new TranscribeAudioAction(new TranscriptionAudio($transcriber, $compressor), $transcriber)
        ->execute($recording->getRealPath(), VoiceNoteFormat::Mp4);

    expect($transcriber->path)->toBe($compressed)
        ->and($transcriber->fileExisted)->toBeTrue()
        ->and(File::exists($compressed))->toBeFalse();
});

it('fails with the "not configured" error, before compressing, when no transcriber is set up', function (): void {
    $compressor = Mockery::mock(AudioCompressor::class);
    $compressor->shouldNotReceive('process');
    $action = new TranscribeAudioAction(new TranscriptionAudio(new NullTranscriber, $compressor), new NullTranscriber);
    $recording = UploadedFile::fake()->create('clip.webm', 5, 'audio/webm');

    expect(fn (): string => $action->execute($recording->getRealPath(), VoiceNoteFormat::Webm))
        ->toThrow(TranscriptionNotConfiguredException::class);
});
