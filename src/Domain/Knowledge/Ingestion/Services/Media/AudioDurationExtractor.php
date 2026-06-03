<?php

namespace Domain\Knowledge\Ingestion\Services\Media;

use Illuminate\Support\Facades\Process;
use RuntimeException;

class AudioDurationExtractor
{
    private function getSupportedMimeTypes(): array
    {
        return array_merge(
            config('mimes.audio', []),
            config('mimes.video', [])
        );
    }

    /**
     * Get audio duration in minutes (rounded up)
     */
    public function getDurationInMinutes(string $filePath): int
    {
        $durationInSeconds = $this->getDurationInSeconds($filePath);

        return (int) ceil($durationInSeconds / 60);
    }

    /**
     * Get audio duration in seconds
     */
    public function getDurationInSeconds(string $filePath): float
    {
        if (! is_file($filePath)) {
            throw new RuntimeException("File not found: {$filePath}");
        }

        // Detect MIME type instead of relying on extension
        $mimeType = mime_content_type($filePath);

        if ($mimeType === false) {
            throw new RuntimeException("Unable to detect MIME type for file: {$filePath}");
        }

        $supportedMimeTypes = $this->getSupportedMimeTypes();

        if (! in_array($mimeType, $supportedMimeTypes, true)) {
            throw new RuntimeException(
                "Unsupported file MIME type '{$mimeType}'. Supported types: ".implode(', ', $supportedMimeTypes)
            );
        }

        // Use ffprobe to extract duration (more efficient than ffmpeg for metadata)
        $result = Process::run([
            $this->ffprobePath(),
            '-v', 'error',
            '-show_entries', 'format=duration',
            '-of', 'default=noprint_wrappers=1:nokey=1',
            $filePath,
        ]);

        if ($result->failed()) {
            throw new RuntimeException(
                "Failed to extract audio duration:\n".$result->errorOutput()
            );
        }

        $duration = trim($result->output());

        if (! is_numeric($duration)) {
            throw new RuntimeException(
                "Invalid duration value returned: {$duration}"
            );
        }

        return (float) $duration;
    }

    private function ffprobePath(): string
    {
        return config('services.bin.ffprobe');
    }
}
