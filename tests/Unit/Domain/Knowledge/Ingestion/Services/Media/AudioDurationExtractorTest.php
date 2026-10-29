<?php

use Domain\Knowledge\Ingestion\Services\Media\AudioDurationExtractor;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * AudioDurationExtractor measures a recording, also a header-less voice note
 * from a browser's MediaRecorder where ffprobe reports no duration, and reads
 * the container headers in PHP when ffprobe is not installed.
 */

/**
 * Build an audio fixture with ffmpeg. When $headerless is true the stream is
 * written to a non-seekable pipe, so ffmpeg can never rewind to stamp the
 * duration into the container header — exactly how a browser's MediaRecorder
 * produces voice notes. ffprobe then reports the container duration as "N/A".
 * The file is deleted after the test.
 */
function adeAudioFixture(float $seconds, string $extension, bool $headerless = false): string
{
    $ffmpeg = config('services.bin.ffmpeg');
    $path = tempTestFile('recording.'.$extension);

    if ($headerless) {
        $format = $extension === 'webm' ? 'webm' : 'mp4';

        Process::run(sprintf(
            '%s -v error -f lavfi -i sine=frequency=440:duration=%s -ac 1 -ar 16000 -c:a libopus -f %s pipe:1 > %s',
            escapeshellarg($ffmpeg),
            escapeshellarg((string) $seconds),
            escapeshellarg($format),
            escapeshellarg($path),
        ))->throw();

        return $path;
    }

    Process::run([
        $ffmpeg, '-v', 'error',
        '-f', 'lavfi', '-i', "sine=frequency=440:duration={$seconds}",
        '-ac', '1', '-ar', '16000',
        $path, '-y',
    ])->throw();

    return $path;
}

/** Whether any of these programs is missing here, so a test that needs it skips. */
function adeMissing(string ...$programs): bool
{
    return collect($programs)->contains(fn (string $program): bool => ! programInstalled($program));
}

it('recovers the duration of a header-less MediaRecorder webm where ffprobe returns N/A', function (): void {
    $file = adeAudioFixture(3, 'webm', headerless: true);
    $probe = Process::run([
        config('services.bin.ffprobe'),
        '-v', 'error', '-show_entries', 'format=duration',
        '-of', 'default=noprint_wrappers=1:nokey=1', $file,
    ]);
    $seconds = app(AudioDurationExtractor::class)->getDurationInSeconds($file);

    expect(Str::trim($probe->output()))->toBe('N/A')
        ->and($seconds)->toBeGreaterThan(2.5)->toBeLessThan(3.6);
})->skip(fn (): bool => adeMissing('ffmpeg', 'ffprobe'), 'needs ffmpeg and ffprobe');

it('reads the duration from the headers in PHP when ffprobe is not installed', function (string $extension): void {
    $file = adeAudioFixture(3, $extension);
    config(['services.bin.ffprobe' => '/nowhere/ffprobe']);

    expect(app(AudioDurationExtractor::class)->getDurationInSeconds($file))->toBeGreaterThan(2.5)->toBeLessThan(3.6);
})->with(['mp3', 'm4a', 'wav', 'ogg'])->skip(fn (): bool => adeMissing('ffmpeg'), 'needs ffmpeg to build the recording');
