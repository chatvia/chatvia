<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Services\Media;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Re-encodes the sound of an audio or video file with ffmpeg into a small
 * MP3 (mono, 16 kHz, 32 kbit/s by default), so long recordings stay under the
 * speech-to-text service's upload limit. ffmpeg reads any container, so one
 * pass also takes the sound out of a video. Used through
 * {@see TranscriptionAudio}, which skips it for a file the service takes as
 * it is. ffmpeg may only open local files (`-protocol_whitelist file`), so a
 * playlist or concat list inside an upload can never make it fetch a URL.
 */
class AudioCompressor
{
    /**
     * Writes the MP3 to a temporary file and returns its path. The caller
     * deletes it. `$inputFormat` forces ffmpeg's demuxer (`-f`) when the
     * format is known; otherwise ffmpeg reads the container from the file.
     */
    public function process(string $input, int $bitrate = 32, ?string $inputFormat = null): string
    {
        if (! File::isFile($input)) {
            throw new RuntimeException("Input file not found: {$input}");
        }

        $output = create_temp_dir('/audio-compression').'/'.Str::uuid().'.mp3';

        $result = Process::run([
            (string) config('services.bin.ffmpeg'),
            '-v', 'error',
            '-protocol_whitelist', 'file',
            ...(filled($inputFormat) ? ['-f', $inputFormat] : []),
            '-i', $input,
            '-vn',
            '-ac', '1',
            '-ar', '16000',
            '-b:a', "{$bitrate}k",
            '-y',
            $output,
        ]);

        if ($result->failed()) {
            File::delete($output);

            throw new RuntimeException("Compression failed:\n".$result->errorOutput());
        }

        return $output;
    }
}
