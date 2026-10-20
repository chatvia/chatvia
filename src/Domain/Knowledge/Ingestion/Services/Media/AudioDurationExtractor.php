<?php

namespace Domain\Knowledge\Ingestion\Services\Media;

use getID3;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Support\Executable;
use Throwable;

/**
 * Reads the duration of an audio or video file. The minutes are the file's
 * length in the sources UI, nothing more, so a file whose length cannot be
 * read gets zero rather than being refused.
 *
 * ffprobe reads it when this server has it; otherwise getID3 reads it in
 * PHP (from the headers, or the clusters of a WebM recording). A browser
 * recording has no length in its header, so with ffprobe, ffmpeg decodes the
 * whole stream to measure it.
 */
class AudioDurationExtractor
{
    /** @return list<string> */
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
        if (! File::isFile($filePath)) {
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

        $duration = Executable::exists($this->ffprobePath())
            ? $this->probeContainerDuration($filePath)
            : $this->headerDuration($filePath);

        // Browser MediaRecorder blobs (audio/webm, audio/mp4) are written as a live,
        // non-seekable stream, so the total duration is never stamped into the
        // container header and ffprobe reports "N/A". Fall back to decoding the
        // whole stream, which always yields a real duration.
        if (! is_numeric($duration) && Executable::exists($this->ffmpegPath())) {
            $duration = $this->decodeDuration($filePath);
        }

        return is_numeric($duration) ? max(0.0, (float) $duration) : 0.0;
    }

    /**
     * Fast path: read the duration straight from the container metadata.
     */
    private function probeContainerDuration(string $filePath): string
    {
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

        return trim($result->output());
    }

    /**
     * Without ffprobe: the duration getID3 reads, or 'N/A' when it finds none.
     */
    private function headerDuration(string $filePath): string
    {
        try {
            $seconds = new getID3()->analyze($filePath)['playtime_seconds'] ?? null;
        } catch (Throwable) {
            return 'N/A';
        }

        return is_numeric($seconds) && $seconds > 0 ? (string) $seconds : 'N/A';
    }

    /**
     * Robust path: decode the entire stream and read the elapsed time ffmpeg
     * reports. Works for header-less streamed containers (e.g. MediaRecorder
     * WebM/MP4) where the container duration is missing.
     */
    private function decodeDuration(string $filePath): string
    {
        $result = Process::run([
            $this->ffmpegPath(),
            '-v', 'error',
            '-stats',
            '-i', $filePath,
            '-f', 'null',
            '-',
        ]);

        // `-stats` prints a running "time=HH:MM:SS.ms" line to stderr; the last one
        // is the total duration decoded. ffmpeg exits 0 while writing to stderr.
        if (preg_match_all('/time=(\d+):(\d{2}):(\d{2}(?:\.\d+)?)/', $result->errorOutput(), $matches, PREG_SET_ORDER) === 0) {
            return 'N/A';
        }

        [, $hours, $minutes, $seconds] = end($matches);

        return (string) ((int) $hours * 3600 + (int) $minutes * 60 + (float) $seconds);
    }

    private function ffprobePath(): string
    {
        return config('services.bin.ffprobe');
    }

    private function ffmpegPath(): string
    {
        return config('services.bin.ffmpeg');
    }
}
