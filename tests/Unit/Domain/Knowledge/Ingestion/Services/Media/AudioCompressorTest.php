<?php

use Domain\Knowledge\Ingestion\Services\Media\AudioCompressor;
use Domain\Knowledge\Ingestion\Services\Media\AudioDurationExtractor;
use Illuminate\Process\PendingProcess;
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

/** A 3-second recording made with ffmpeg, with a picture too when it is a video; deleted after the test. */
function acRecording(string $extension, bool $video = false): string
{
    $path = tempTestFile('recording.'.$extension);

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

    $mp3 = deleteAfterTest(app(AudioCompressor::class)->process($recording));

    expect($mp3)->toEndWith('.mp3')
        ->and(filesize($mp3))->toBeLessThan(filesize($recording) + 20_000)
        ->and(app(AudioDurationExtractor::class)->getDurationInSeconds($mp3))->toBeGreaterThan(2.5)->toBeLessThan(3.6);
})->with([
    'ogg' => ['ogg', false],
    'flac' => ['flac', false],
    'mov' => ['mov', true],
    'mkv' => ['mkv', true],
]);

it('reads a voice note with the demuxer it is told to use', function (): void {
    $recording = acRecording('webm');

    $mp3 = deleteAfterTest(app(AudioCompressor::class)->process($recording, inputFormat: 'webm'));

    expect($mp3)->toEndWith('.mp3');
});

it('never reads a file as another format than the one it is told', function (): void {
    // An HLS playlist saved under an audio name: ffmpeg would follow its
    // entries if it picked the demuxer from the content.
    $playlist = tempTestFile('recording.webm', "#EXTM3U\n#EXTINF:1,\nhttp://example.invalid/a.ts\n");

    expect(fn (): string => app(AudioCompressor::class)->process($playlist, inputFormat: 'webm'))
        ->toThrow(RuntimeException::class, 'Compression failed');
});

it('lets ffmpeg open local files only, with the demuxer it is told when there is one', function (?string $inputFormat, array $demuxer): void {
    Process::fake();
    $recording = tempTestFile('recording.webm', 'bytes');

    app(AudioCompressor::class)->process($recording, inputFormat: $inputFormat);

    Process::assertRan(fn (PendingProcess $process): bool => array_slice($process->command, 1, 4 + count($demuxer) + 2) === ['-v', 'error', '-protocol_whitelist', 'file', ...$demuxer, '-i', $recording]);
})->with([
    'a voice note' => ['webm', ['-f', 'webm']],
    'a knowledge file' => [null, []],
]);

it('fails on a file ffmpeg cannot read, and leaves no MP3 behind', function (): void {
    $folder = create_temp_dir('/audio-compression');
    $before = glob("{$folder}/*.mp3") ?: [];

    expect(fn (): string => app(AudioCompressor::class)->process(tempTestFile('broken.ogg', 'not audio')))
        ->toThrow(RuntimeException::class, 'Compression failed');

    expect(glob("{$folder}/*.mp3") ?: [])->toBe($before);
});
