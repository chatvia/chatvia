<?php

use Domain\Knowledge\Ingestion\Services\Media\AudioCompressor;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

/**
 * AudioCompressor re-encodes the sound of any audio or video file into a
 * small MP3 in one ffmpeg pass. ffmpeg may open local files only, with the
 * demuxer it is told, so a playlist saved under an audio name is never
 * followed to another address.
 */
beforeEach(function (): void {
    if (! programInstalled('ffmpeg')) {
        $this->markTestSkipped('ffmpeg binary not available');
    }
});

it('never reads a file as another format than the one it is told', function (): void {
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
