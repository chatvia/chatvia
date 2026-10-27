<?php

use Domain\Knowledge\Ingestion\Services\Media\AudioCompressor;
use Domain\Knowledge\Ingestion\Services\Media\TranscriptionAudio;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\Support\Llm\FakeTranscriber;

/**
 * TranscriptionAudio sends a recording as it is when the speech-to-text
 * service takes it, and an MP3 made with ffmpeg otherwise. Without ffmpeg,
 * only what the service takes as it is can be turned into text. The service
 * here takes MP3 and WebM up to 100 bytes, like one with a tiny limit.
 */
it('can turn a file into text as it is, or after ffmpeg compressed it', function (): void {
    $audio = new TranscriptionAudio(FakeTranscriber::takingAsIs(['mp3', 'webm'], maxBytes: 100), new AudioCompressor);
    $small = tempTestFile('small.webm', Str::repeat('a', 50));
    $large = tempTestFile('large.webm', Str::repeat('a', 500));

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
    $file = tempTestFile('note.mp3', 'mp3');

    $sent = new TranscriptionAudio(FakeTranscriber::takingAsIs(['mp3', 'webm'], maxBytes: 100), $compressor)->withPrepared($file, fn (string $path): string => $path);

    expect($sent)->toBe($file)
        ->and(File::exists($file))->toBeTrue();
});

it('hands over a compressed copy otherwise, and deletes it afterwards, also on failure', function (): void {
    $compressed = tempTestFile('out.mp3', 'mp3');
    $compressor = Mockery::mock(AudioCompressor::class);
    $compressor->shouldReceive('process')->once()->andReturn($compressed);
    $file = tempTestFile('note.m4a', 'm4a');

    expect(fn (): mixed => new TranscriptionAudio(FakeTranscriber::takingAsIs(['mp3', 'webm'], maxBytes: 100), $compressor)->withPrepared($file, function (string $path) use ($compressed): never {
        expect($path)->toBe($compressed);

        throw new RuntimeException('The service failed.');
    }))->toThrow(RuntimeException::class, 'The service failed.');

    expect(File::exists($compressed))->toBeFalse()
        ->and(File::exists($file))->toBeTrue();
});
