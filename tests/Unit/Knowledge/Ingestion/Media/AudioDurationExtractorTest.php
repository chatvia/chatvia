<?php

use Domain\Knowledge\Ingestion\Services\Media\AudioDurationExtractor;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\ExecutableFinder;

/**
 * Build an audio fixture with ffmpeg. When $headerless is true the stream is
 * written to a non-seekable pipe, so ffmpeg can never rewind to stamp the
 * duration into the container header — exactly how a browser's MediaRecorder
 * produces voice notes. ffprobe then reports the container duration as "N/A".
 */
function adeAudioFixture(float $seconds, string $extension, bool $headerless = false): string
{
    $ffmpeg = config('services.bin.ffmpeg');
    $path = tempnam(sys_get_temp_dir(), 'aud_').'.'.$extension;

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

beforeEach(function (): void {
    foreach (['ffmpeg', 'ffprobe'] as $bin) {
        // The config holds either an absolute path or a bare name looked up on PATH.
        $configured = (string) config("services.bin.{$bin}");

        if (! is_file($configured) && (new ExecutableFinder)->find($configured) === null) {
            $this->markTestSkipped("{$bin} binary not available");
        }
    }
});

it('reads the duration from a normal container header', function (): void {
    $file = adeAudioFixture(3, 'mp3');

    $seconds = app(AudioDurationExtractor::class)->getDurationInSeconds($file);

    expect($seconds)->toBeGreaterThan(2.5)->toBeLessThan(3.6);

    unlink($file);
});

it('recovers the duration of a header-less MediaRecorder webm where ffprobe returns N/A', function (): void {
    // A recorded voice note (audio/webm from MediaRecorder) has no duration in
    // its header, so ffprobe answers "N/A".
    $file = adeAudioFixture(3, 'webm', headerless: true);

    // Guard: confirm the fixture actually reproduces the N/A container metadata,
    // otherwise this test would pass via the fast path and prove nothing.
    $probe = Process::run([
        config('services.bin.ffprobe'),
        '-v', 'error', '-show_entries', 'format=duration',
        '-of', 'default=noprint_wrappers=1:nokey=1', $file,
    ]);
    expect(trim($probe->output()))->toBe('N/A');

    $seconds = app(AudioDurationExtractor::class)->getDurationInSeconds($file);

    expect($seconds)->toBeGreaterThan(2.5)->toBeLessThan(3.6);

    unlink($file);
});

it('rounds the duration up to whole minutes', function (): void {
    $file = adeAudioFixture(3, 'webm', headerless: true);

    expect(app(AudioDurationExtractor::class)->getDurationInMinutes($file))->toBe(1);

    unlink($file);
});
