<?php

use Domain\Knowledge\Ingestion\Services\Media\AudioCompressor;
use Domain\Knowledge\Ingestion\Services\Media\TranscriptionAudio;
use Domain\Llm\Contracts\Transcriber;

/**
 * TranscriptionAudio sends a recording as it is when the speech-to-text
 * service takes it, and an MP3 made with ffmpeg otherwise. Without ffmpeg,
 * only what the service takes as it is can be turned into text.
 */
function tauTranscriber(): Transcriber
{
    // Takes MP3 and WebM up to 100 bytes, like a service with a tiny limit.
    return new class implements Transcriber
    {
        public function transcribe(string $filePath): string
        {
            return 'text';
        }

        public function takesAsIs(string $extension, int $bytes): bool
        {
            return in_array($extension, ['mp3', 'webm'], true) && $bytes > 0 && $bytes <= 100;
        }

        public function isEnabled(): bool
        {
            return true;
        }

        public function name(): string
        {
            return 'fake';
        }
    };
}

it('can turn a file into text as it is, or after ffmpeg compressed it', function (): void {
    $audio = new TranscriptionAudio(tauTranscriber(), new AudioCompressor);
    $small = create_temp_file('small.webm', str_repeat('a', 50));
    $large = create_temp_file('large.webm', str_repeat('a', 500));

    config(['services.bin.ffmpeg' => '/bin/sh']);

    expect($audio->canTranscribe($small, 'webm'))->toBeTrue()
        ->and($audio->canTranscribe($large, 'webm'))->toBeTrue()
        ->and($audio->canTranscribe($small, 'mov'))->toBeTrue();

    config(['services.bin.ffmpeg' => '/nowhere/ffmpeg']);

    expect($audio->canTranscribe($small, 'WEBM'))->toBeTrue()
        ->and($audio->canTranscribe($large, 'webm'))->toBeFalse()
        ->and($audio->canTranscribe($small, 'mov'))->toBeFalse()
        ->and($audio->readsFormat('webm'))->toBeTrue()
        ->and($audio->readsFormat('mov'))->toBeFalse();
});

it('hands over the file itself when the service takes it, without compressing it', function (): void {
    $compressor = Mockery::mock(AudioCompressor::class);
    $compressor->shouldNotReceive('process');
    $file = create_temp_file('note.mp3', 'mp3');

    $sent = new TranscriptionAudio(tauTranscriber(), $compressor)->withPrepared($file, fn (string $path): string => $path);

    expect($sent)->toBe($file)
        ->and(file_exists($file))->toBeTrue();
});

it('hands over a compressed copy otherwise, and deletes it afterwards, also on failure', function (): void {
    $compressed = create_temp_file('out.mp3', 'mp3');
    $compressor = Mockery::mock(AudioCompressor::class);
    $compressor->shouldReceive('process')->once()->andReturn($compressed);
    $file = create_temp_file('note.m4a', 'm4a');

    expect(fn (): mixed => new TranscriptionAudio(tauTranscriber(), $compressor)->withPrepared($file, function (string $path) use ($compressed): never {
        expect($path)->toBe($compressed);

        throw new RuntimeException('The service failed.');
    }))->toThrow(RuntimeException::class, 'The service failed.');

    expect(file_exists($compressed))->toBeFalse()
        ->and(file_exists($file))->toBeTrue();
});
