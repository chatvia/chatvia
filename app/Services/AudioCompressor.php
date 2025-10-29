<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class AudioCompressor
{
    private const array SUPPORTED_INPUTS = [
        'mp3', 'mp4', 'mpeg', 'mpga', 'm4a', 'wav', 'webm',
    ];

    private function ffmpegPath(): string
    {
        return config('services.bin.ffmpeg');
    }

    public function process(
        string $input,
        string $codec = 'mp3',
        int $bitrate = 32
    ): string {

        if (! is_file($input)) {
            throw new RuntimeException("Input file not found: {$input}");
        }

        $ext = strtolower(pathinfo($input, PATHINFO_EXTENSION));

        if (! in_array($ext, self::SUPPORTED_INPUTS, true)) {
            throw new InvalidArgumentException(
                "Unsupported input format '{$ext}'. Allowed: ".implode(', ', self::SUPPORTED_INPUTS)
            );
        }

        // If input is video (e.g., MP4 / WebM), extract audio first
        $workingInput = $input;
        if (in_array($ext, ['mp4', 'webm'], true)) {
            $workingInput = $this->extractAudio($input);
        }

        // Generate output file extension based on codec
        $outputExt = match ($codec) {
            'opus' => 'opus',
            'wav' => 'wav',
            default => 'mp3',
        };

        $output = temp_dir('/audio-compression').'/'.Str::uuid().".{$outputExt}";

        // Compress result to desired output format
        $this->compress($workingInput, $output, $codec, $bitrate);

        // If we created an intermediate audio file, delete it
        if ($workingInput !== $input && file_exists($workingInput)) {
            unlink($workingInput);
        }

        return $output;
    }

    /**
     * Extract audio track from video input as a temporary WAV
     */
    private function extractAudio(string $input): string
    {
        $temp = temp_dir('/audio-compression').'/'.uniqid('', true).'.wav';

        $result = Process::run([
            $this->ffmpegPath(),
            '-i', $input,
            '-vn',
            '-ac', '1',
            '-ar', '16000',
            '-c:a', 'pcm_s16le',
            $temp,
            '-y',
        ]);

        if ($result->failed()) {
            throw new RuntimeException(
                "Failed to extract audio:\n".$result->errorOutput()
            );
        }

        return $temp;
    }

    /**
     * Compress audio to final format for Whisper
     */
    private function compress(
        string $input,
        string $output,
        string $codec,
        int $bitrate
    ): void {

        $codec = strtolower($codec);

        $args = match ($codec) {
            'opus' => [
                $this->ffmpegPath(),
                '-i', $input,
                '-ac', '1',
                '-ar', '16000',
                '-c:a', 'libopus',
                '-b:a', "{$bitrate}k",
                $output,
                '-y',
            ],
            'mp3' => [
                $this->ffmpegPath(),
                '-i', $input,
                '-ac', '1',
                '-ar', '16000',
                '-b:a', "{$bitrate}k",
                $output,
                '-y',
            ],
            'wav' => [
                $this->ffmpegPath(),
                '-i', $input,
                '-ac', '1',
                '-ar', '16000',
                '-c:a', 'pcm_s16le',
                $output,
                '-y',
            ],
            default => throw new InvalidArgumentException(
                "Unsupported codec '{$codec}'. Use: opus, mp3, wav."
            )
        };

        $result = Process::run($args);

        if ($result->failed()) {
            throw new RuntimeException(
                "Compression failed:\n".$result->errorOutput()
            );
        }
    }
}
