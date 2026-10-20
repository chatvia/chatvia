<?php

use Domain\Knowledge\Ingestion\Services\Media\AudioDurationExtractor;
use Illuminate\Support\Facades\Process;

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
        if (! programInstalled($bin)) {
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

it('reads the duration from the headers in PHP when ffprobe is not installed', function (string $extension): void {
    $file = adeAudioFixture(3, $extension);
    config(['services.bin.ffprobe' => '/nowhere/ffprobe']);

    expect(app(AudioDurationExtractor::class)->getDurationInSeconds($file))->toBeGreaterThan(2.5)->toBeLessThan(3.6);

    unlink($file);
})->with(['mp3', 'm4a', 'wav', 'ogg']);

it('still measures a header-less recording with ffmpeg when ffprobe is not installed', function (): void {
    $file = adeAudioFixture(3, 'webm', headerless: true);
    config(['services.bin.ffprobe' => '/nowhere/ffprobe']);

    expect(app(AudioDurationExtractor::class)->getDurationInSeconds($file))->toBeGreaterThan(2.5)->toBeLessThan(3.6);

    unlink($file);
});

it('measures a header-less recording in PHP when neither ffprobe nor ffmpeg is installed', function (): void {
    $file = adeAudioFixture(3, 'webm', headerless: true);
    config(['services.bin.ffprobe' => '/nowhere/ffprobe', 'services.bin.ffmpeg' => '/nowhere/ffmpeg']);

    expect(app(AudioDurationExtractor::class)->getDurationInSeconds($file))->toBeGreaterThan(2.5)->toBeLessThan(3.6);

    unlink($file);
});

it('gives zero for audio whose length nothing can read', function (): void {
    // A WAV header with no sound after it.
    $format = pack('vvVVvv', 1, 1, 16000, 32000, 2, 16);
    $file = create_temp_file('empty.wav', 'RIFF'.pack('V', 36).'WAVEfmt '.pack('V', 16).$format.'data'.pack('V', 0));
    config(['services.bin.ffprobe' => '/nowhere/ffprobe', 'services.bin.ffmpeg' => '/nowhere/ffmpeg']);

    expect(app(AudioDurationExtractor::class)->getDurationInMinutes($file))->toBe(0);
});
