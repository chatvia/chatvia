<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Services\Media;

use Closure;
use Domain\Llm\Contracts\Transcriber;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Support\Executable;

/**
 * Prepares a recording for the speech-to-text service: the file itself when
 * the service takes it as it is (its format, under its size limit), which
 * keeps the sound as recorded; otherwise an MP3 made with ffmpeg
 * ({@see AudioCompressor}). On a server without ffmpeg, only files the
 * service takes as they are can be turned into text ({@see canTranscribe()}),
 * so the upload refuses the others at once.
 */
class TranscriptionAudio
{
    public function __construct(
        private readonly Transcriber $transcriber,
        private readonly AudioCompressor $compressor,
    ) {}

    /** Whether this server can turn the file into text: as it is, or after ffmpeg compressed it. */
    public function canTranscribe(string $path, string $extension): bool
    {
        return $this->takesAsIs($path, $extension) || $this->hasFfmpeg();
    }

    /** Whether the service takes the file as it is. */
    public function takesAsIs(string $path, string $extension): bool
    {
        return $this->transcriber->takesAsIs(Str::lower($extension), (int) @filesize($path));
    }

    /** Whether the service reads files of this format at all (at some size). */
    public function readsFormat(string $extension): bool
    {
        return $this->transcriber->takesAsIs(Str::lower($extension), 1);
    }

    public function hasFfmpeg(): bool
    {
        return Executable::exists((string) config('services.bin.ffmpeg'));
    }

    /**
     * Runs `$use` with the file to send: the one at `$path` (its extension
     * names its format), or a compressed copy that is deleted afterwards.
     * `$inputFormat` pins the ffmpeg demuxer for a file whose format is known
     * (a visitor's voice note), so ffmpeg never guesses one from its content.
     *
     * @template T
     *
     * @param  Closure(string): T  $use
     * @return T
     */
    public function withPrepared(string $path, Closure $use, ?string $inputFormat = null): mixed
    {
        if ($this->takesAsIs($path, pathinfo($path, PATHINFO_EXTENSION))) {
            return $use($path);
        }

        $compressed = $this->compressor->process($path, inputFormat: $inputFormat);

        try {
            return $use($compressed);
        } finally {
            File::delete($compressed);
        }
    }
}
