<?php

use Domain\Knowledge\Ingestion\Services\Media\AudioCompressor;
use Domain\Knowledge\Ingestion\Services\Media\AudioDurationExtractor;
use Illuminate\Support\Facades\Process;

/**
 * AudioCompressor re-encodes the sound of any audio or video file into a
 * small MP3 in one ffmpeg pass, for formats the speech-to-text service does
 * not take and recordings over its size limit.
 */
beforeEach(function (): void {
    if (! programInstalled('ffmpeg')) {
        $this->markTestSkipped('ffmpeg binary not available');
    }
});

/** A 3-second recording made with ffmpeg, with a picture too when it is a video. */
function acRecording(string $extension, bool $video = false): string
{
    $path = tempnam(sys_get_temp_dir(), 'rec_').'.'.$extension;

    Process::run([
        config('services.bin.ffmpeg'), '-v', 'error',
        ...($video ? ['-f', 'lavfi', '-i', 'testsrc=size=160x120:rate=5:duration=3'] : []),
        '-f', 'lavfi', '-i', 'sine=frequency=440:duration=3',
        '-shortest', $path, '-y',
    ])->throw();

    return $path;
}

it('turns audio and video of any format into a short MP3', function (string $extension, bool $video): void {
    $recording = acRecording($extension, $video);

    $mp3 = app(AudioCompressor::class)->process($recording);

    expect($mp3)->toEndWith('.mp3')
        ->and(filesize($mp3))->toBeLessThan(filesize($recording) + 20_000)
        ->and(app(AudioDurationExtractor::class)->getDurationInSeconds($mp3))->toBeGreaterThan(2.5)->toBeLessThan(3.6);

    unlink($mp3);
    unlink($recording);
})->with([
    'ogg' => ['ogg', false],
    'flac' => ['flac', false],
    'mov' => ['mov', true],
    'mkv' => ['mkv', true],
]);

it('fails on a file ffmpeg cannot read, and leaves no MP3 behind', function (): void {
    $folder = create_temp_dir('/audio-compression');
    $before = glob("{$folder}/*.mp3") ?: [];

    expect(fn (): string => app(AudioCompressor::class)->process(create_temp_file('broken.ogg', 'not audio')))
        ->toThrow(RuntimeException::class, 'Compression failed');

    expect(glob("{$folder}/*.mp3") ?: [])->toBe($before);
});
